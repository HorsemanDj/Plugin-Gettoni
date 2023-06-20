<?php
/*
Plugin Name: Gettoni Plugin
Plugin URI: #
Description: Gestione gettoni utente
Version: 1
Author: Riccardi Gaetano
Author URI: https://www.riccardigaetano.it
License: GPL2
*/

/// Azione all'attivazione del plugin
register_activation_hook( __FILE__, 'gettoni_plugin_activate' );
function gettoni_plugin_activate() {
    // Creazione della voce "gettone" per tutti gli utenti esistenti
    $users = get_users();
    foreach ($users as $user) {
        add_user_meta( $user->ID, 'gettone', 0, true ); // Imposta il valore del gettone come preferisci all'attivazione del plugin
    }

    // Creazione della tabella per le voci nel database
    global $wpdb;
    $table_name = $wpdb->prefix . 'gettoni_voci';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        lavoro varchar(255) NOT NULL,
        referenza varchar(255) NOT NULL,
        quantita int(11) NOT NULL,
        user_id int(11) NOT NULL DEFAULT 0,
        count int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    // Creazione della tabella per i lavori presi dagli utenti nel database
    $table_name_jobs = $wpdb->prefix . 'gettoni_lavori_presi';

    $sql_jobs = "CREATE TABLE $table_name_jobs (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id int(11) NOT NULL,
        voce_id int(11) NOT NULL,
        count int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY voce_id (voce_id)
    ) $charset_collate;";

    dbDelta($sql_jobs);
}


// Azione alla disattivazione del plugin
register_deactivation_hook( __FILE__, 'gettoni_plugin_deactivate' );
function gettoni_plugin_deactivate() {
    // Rimozione della voce "gettone" per tutti gli utenti
    $users = get_users();
    foreach ($users as $user) {
        delete_user_meta( $user->ID, 'gettone' );
    }

    // Rimozione della tabella delle voci dal database
    global $wpdb;
    $table_name = $wpdb->prefix . 'gettoni_voci';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}

// Funzione per creare il menu nel backend
add_action( 'admin_menu', 'gettoni_plugin_add_menu' );
function gettoni_plugin_add_menu() {
    $main_menu_slug = 'gettoni-plugin-menu';

    add_menu_page(
        'Gettoni', // Titolo della voce di menu
        'Gettoni', // Etichetta del menu
        'manage_options',
        $main_menu_slug, // Slug del menu principale
        'gettoni_plugin_dashboard_page', // Callback per la pagina
        'dashicons-money-alt', // Icona del menu (puoi scegliere un'icona da https://developer.wordpress.org/resource/dashicons/)
        2 // Posizione del menu nel menu di amministrazione
    );

    add_submenu_page(
        $main_menu_slug, // Slug del menu principale
        'Dashboard', // Titolo della sottovoce
        'Dashboard', // Etichetta della sottovoce
        'manage_options',
        $main_menu_slug, // Slug della sottovoce (lo stesso del menu principale)
        'gettoni_plugin_dashboard_page' // Callback per la pagina
    );

    add_submenu_page(
        $main_menu_slug, // Slug del menu principale
        'Aggiungi Gettoni', // Titolo della sottovoce
        'Aggiungi Gettoni', // Etichetta della sottovoce
        'manage_options',
        'gettoni-plugin-add-gettoni', // Slug della sottovoce
        'gettoni_plugin_add_gettoni_page' // Callback per la pagina
    );

    add_submenu_page(
        $main_menu_slug, // Slug del menu principale
        'Voci', // Titolo della sottovoce
        'Voci', // Etichetta della sottovoce
        'manage_options',
        'gettoni-plugin-voci', // Slug della sottovoce
        'gettoni_plugin_voci_page' // Callback per la pagina
    );

    add_submenu_page(
        $main_menu_slug, // Slug del menu principale
        'Info', // Titolo della sottovoce
        'Info', // Etichetta della sottovoce
        'manage_options',
        'gettoni-plugin-info', // Slug della sottovoce
        'gettoni_plugin_info_page' // Callback per la pagina
    );
}


// Callback per la pagina di dashboard
function gettoni_plugin_dashboard_page() {
    global $wpdb;
    $table_name_jobs = $wpdb->prefix . 'gettoni_lavori_presi';
    $table_name_voci = $wpdb->prefix . 'gettoni_voci';

    $users = get_users();

    echo '<div class="wrap">';
    echo '<h1>Dashboard</h1>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>Nome Utente</th><th>Gettone</th></tr></thead>';
    echo '<tbody>';

    foreach ($users as $user) {
        $gettone = get_user_meta($user->ID, 'gettone', true);

        if ($gettone > 0) {
            echo '<tr>';
            echo '<td>' . $user->user_login . '</td>';
            echo '<td>' . $gettone . '</td>';
            echo '</tr>';
        }
    }

    echo '</tbody>';
    echo '</table>';

    echo '<h2>Lavori Presi</h2>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>Nome Utente</th><th>Lavoro</th><th>Referenza</th><th>Quantità Presa</th></tr></thead>';
    echo '<tbody>';

    foreach ($users as $user) {
        $user_id = $user->ID;

        $taken_jobs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT voci.lavoro, voci.referenza, jobs.count
                FROM $table_name_jobs AS jobs
                INNER JOIN $table_name_voci AS voci ON jobs.voce_id = voci.id 
                WHERE jobs.user_id = %d",
                $user_id
            )
        );

        if ($taken_jobs) {
            foreach ($taken_jobs as $job) {
                echo '<tr>';
                echo '<td>' . $user->user_login . '</td>';
                echo '<td>' . $job->lavoro . '</td>';
                echo '<td>' . $job->referenza . '</td>';
                echo '<td>' . $job->count . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr>';
            echo '<td>' . $user->user_login . '</td>';
            echo '<td colspan="3">Nessun lavoro preso</td>';
            echo '</tr>';
        }
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}



// Callback per la pagina "Aggiungi Gettoni"
function gettoni_plugin_add_gettoni_page() {
    if (isset($_POST['add_gettoni'])) {
        $user_id = $_POST['user_id'];
        $quantity = $_POST['quantity'];

        if ($quantity > 0) {
            $current_gettone = get_user_meta($user_id, 'gettone', true);
            $new_gettone = $current_gettone + $quantity;
            update_user_meta($user_id, 'gettone', $new_gettone);
        }
    }

    if (isset($_POST['subtract_gettoni'])) {
        $user_id = $_POST['user_id'];
        $quantity = $_POST['quantity'];

        if ($quantity > 0) {
            $current_gettone = get_user_meta($user_id, 'gettone', true);
            $new_gettone = $current_gettone - $quantity;

            if ($new_gettone >= 0) {
                update_user_meta($user_id, 'gettone', $new_gettone);
            }
        }
    }

    $users = get_users();
    ?>

    <div class="wrap">
        <h1>Aggiungi Gettoni</h1>

        <form method="POST">
            <label for="user_id">Utente:</label>
            <select name="user_id" id="user_id">
                <?php foreach ($users as $user) : ?>
                    <option value="<?php echo $user->ID; ?>"><?php echo $user->user_login; ?></option>
                <?php endforeach; ?>
            </select>

            <label for="quantity">Quantità:</label>
            <input type="number" name="quantity" id="quantity" min="1" required>

            <button type="submit" name="add_gettoni">Aggiungi</button>
            <button type="submit" name="subtract_gettoni">Sottrai</button>
        </form>
    </div>
    <?php
}

// Callback per la pagina di info
function gettoni_plugin_info_page() {
    ?>
    <div class="wrap">
        <h1>Informazioni sul Plugin Gettoni</h1>
        
        <h2>Descrizione</h2>
        <p>Il plugin Gettoni consente di gestire i gettoni degli utenti e tenere traccia dei lavori presi.</p>
        
        <h2>Utilizzo dello Shortcode</h2>
        <p>Puoi utilizzare lo shortcode [gettoni_voci] per visualizzare la tabella delle voci del plugin nei tuoi contenuti. Puoi inserire lo shortcode in qualsiasi pagina o articolo.</p>
        <p>Lo shortcode mostrerà una tabella con i dettagli delle voci disponibili, inclusi il lavoro, la referenza e la quantità.</p>
        <p>Esempio: [gettoni_voci]</p>
        
        <h2>Aggiunta e Rimozione Gettoni</h2>
        <p>Nella pagina "Aggiungi Gettoni" puoi assegnare o sottrarre gettoni agli utenti. Seleziona l'utente desiderato, specifica la quantità di gettoni da aggiungere o sottrarre e fai clic sul pulsante corrispondente.</p>
        
        <h2>Gestione delle Voci</h2>
        <p>Nella pagina "Voci" puoi aggiungere, modificare o eliminare le voci disponibili. Puoi specificare il nome del lavoro, la referenza e la quantità disponibile per ciascuna voce.</p>
        
        <h2>Lavori Presi dagli Utenti</h2>
        <p>Nella pagina "Lavori Presi" puoi vedere i lavori presi dagli utenti e il numero di volte in cui sono stati presi. La tabella mostrerà il nome dell'utente, il lavoro preso e la quantità.</p>
        
        <h2>Assistenza</h2>
        <p>Se hai domande o incontri problemi nell'utilizzo del plugin, puoi contattare il nostro team di supporto all'indirizzo support@gettoni-plugin.com.</p>
    </div>
    <?php
}

// Callback per la pagina delle voci
function gettoni_plugin_voci_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gettoni_voci';

    // Aggiunta di una voce
    if (isset($_POST['add_voce'])) {
        $lavoro = sanitize_text_field($_POST['lavoro']);
        $referenza = sanitize_text_field($_POST['referenza']);
        $quantita = intval($_POST['quantita']);

        $wpdb->insert(
            $table_name,
            array(
                'lavoro' => $lavoro,
                'referenza' => $referenza,
                'quantita' => $quantita,
                'quantita' => $quantita,
            ),
            array('%s', '%s', '%d', '%d')
        );
    }

    // Eliminazione di una voce
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['voce_id'])) {
        $voce_id = intval($_GET['voce_id']);
        $wpdb->delete($table_name, array('id' => $voce_id), array('%d'));
    }

    // Modifica di una voce
    if (isset($_POST['edit_voce'])) {
        $voce_id = intval($_POST['voce_id']);
        $lavoro = sanitize_text_field($_POST['lavoro']);
        $referenza = sanitize_text_field($_POST['referenza']);
        $quantita = intval($_POST['quantita']);

        $wpdb->update(
            $table_name,
            array(
                'lavoro' => $lavoro,
                'referenza' => $referenza,
                'quantita' => $quantita,
                'quantita' => $quantita,
            ),
            array('id' => $voce_id),
            array('%s', '%s', '%d', '%d'),
            array('%d')
        );
    }

    // Recupera tutte le voci dal database
    $voci = $wpdb->get_results("SELECT * FROM $table_name");

    ?>
    <div class="wrap">
        <h1>Voci</h1>

        <!-- Form per aggiungere una voce -->
        <h2>Aggiungi Voce</h2>
        <form method="POST">
            <label for="lavoro">Lavoro:</label>
            <input type="text" name="lavoro" id="lavoro" required>

            <label for="referenza">Referenza:</label>
            <input type="text" name="referenza" id="referenza" required>

            <label for="quantita">Quantità:</label>
            <input type="number" name="quantita" id="quantita" min="1" required>

            <button type="submit" name="add_voce">Aggiungi</button>
        </form>

        <!-- Tabella delle voci -->
        <h2>Elenco Voci</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Lavoro</th>
                    <th>Referenza</th>
                    <th>Quantità</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($voci as $voce) : ?>
                    <tr>
                        <td><?php echo $voce->lavoro; ?></td>
                        <td><?php echo $voce->referenza; ?></td>
                        <td><?php echo $voce->quantita; ?></td>
                        <td>
                            <a href="?page=gettoni-plugin-voci&action=edit&voce_id=<?php echo $voce->id; ?>">Modifica</a>
                            <a href="?page=gettoni-plugin-voci&action=delete&voce_id=<?php echo $voce->id; ?>">Elimina</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Form per modificare una voce -->
        <?php if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['voce_id'])) : ?>
            <?php
            $voce_id = intval($_GET['voce_id']);
            $voce = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $voce_id));
            ?>
            <h2>Modifica Voce</h2>
            <form method="POST">
                <input type="hidden" name="voce_id" value="<?php echo $voce_id; ?>">

                <label for="lavoro">Lavoro:</label>
                <input type="text" name="lavoro" id="lavoro" value="<?php echo $voce->lavoro; ?>" required>

                <label for="referenza">Referenza:</label>
                <input type="text" name="referenza" id="referenza" value="<?php echo $voce->referenza; ?>" required>

                <label for="quantita">Quantità:</label>
                <input type="number" name="quantita" id="quantita" min="1" value="<?php echo $voce->quantita; ?>" required>

                <button type="submit" name="edit_voce">Salva Modifiche</button>
            </form>
        <?php endif; ?>
    </div>
    <?php
}

// Callback per la pagina dei lavori presi
function gettoni_plugin_lavori_presi_page()
{
    global $wpdb;
    $table_name_jobs = $wpdb->prefix . 'gettoni_lavori_presi';
    $table_name_voci = $wpdb->prefix . 'gettoni_voci';

    $users = get_users();

    echo '<div class="wrap">';
    echo '<h1>Lavori Presi</h1>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>Nome Utente</th><th>Lavoro</th><th>Quantità</th></tr></thead>';
    echo '<tbody>';

    foreach ($users as $user) {
        $user_id = $user->ID;

        $taken_jobs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT jobs.*, voci.lavoro 
                FROM $table_name_jobs AS jobs 
                INNER JOIN $table_name_voci AS voci ON jobs.voce_id = voci.id 
                WHERE jobs.user_id = %d",
                $user_id
            )
        );

        if ($taken_jobs) {
            foreach ($taken_jobs as $job) {
                echo '<tr>';
                echo '<td>' . $user->user_login . '</td>';
                echo '<td>' . $job->lavoro . '</td>';
                echo '<td>' . $job->count . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr>';
            echo '<td>' . $user->user_login . '</td>';
            echo '<td colspan="2">Nessun lavoro preso</td>';
            echo '</tr>';
        }
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}


// Funzione per generare l'output dello shortcode
function gettoni_plugin_voci_shortcode() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gettoni_voci';

    // Recupera tutte le voci dal database
    $voci = $wpdb->get_results("SELECT * FROM $table_name");

    $output = '<table class="gettoni-voci-table">';
    $output .= '<thead><tr><th>Lavoro</th><th>Referenza</th><th>Quantità</th><th>Azioni</th></tr></thead>';
    $output .= '<tbody>';

    foreach ($voci as $voce) {
        $output .= '<tr>';
        $output .= '<td>' . $voce->lavoro . '</td>';
        $output .= '<td>' . $voce->referenza . '</td>';
        $output .= '<td>' . $voce->quantita . '</td>';

        // Verifica se l'utente ha abbastanza gettoni e se la quantità è maggiore di 0
        $current_user_id = get_current_user_id();
        $user_gettone = get_user_meta($current_user_id, 'gettone', true);

        if ($user_gettone > 0 && $voce->quantita > 0) {
            $output .= '<td>';
            $output .= '<form method="POST">';
            $output .= '<input type="hidden" name="voce_id" value="' . $voce->id . '">';
            $output .= '<button type="submit" name="prendi_lavoro">Prendi Lavoro</button>';
            $output .= '</form>';
            $output .= '</td>';
        } else {
            $output .= '<td>Non disponibile</td>';
        }

        $output .= '</tr>';
    }

    $output .= '</tbody>';
    $output .= '</table>';

    return $output;
}
add_shortcode('gettoni_voci', 'gettoni_plugin_voci_shortcode');

// Azione per il prendi lavoro
add_action('init', 'gettoni_plugin_prende_lavoro');
function gettoni_plugin_prende_lavoro()
{
    if (isset($_POST['prendi_lavoro'])) {
        global $wpdb;
        $table_name_jobs = $wpdb->prefix . 'gettoni_lavori_presi';
        $table_name_voci = $wpdb->prefix . 'gettoni_voci';
        $voce_id = intval($_POST['voce_id']);

        // Verifica se l'utente ha abbastanza gettoni e se la quantità è maggiore di 0
        $current_user_id = get_current_user_id();
        $user_gettone = get_user_meta($current_user_id, 'gettone', true);

        $voce = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name_voci WHERE id = %d", $voce_id));
        $voce_quantita = $voce->quantita;
        $voce_quantita = $voce->quantita;

        if ($user_gettone > 0 && $voce_quantita > 0 && $voce_quantita > 0) {
            // Inizia una transazione per garantire l'integrità dei dati
            $wpdb->query('START TRANSACTION');

            try {
                // Aggiorna il numero di gettoni dell'utente
                $new_user_gettone = $user_gettone - 1;
                update_user_meta($current_user_id, 'gettone', $new_user_gettone);

                // Aggiorna la quantità disponibile del lavoro
                $new_voce_quantita = $voce_quantita - 1;
                $wpdb->update(
                    $table_name_voci,
                    array('quantita' => $new_voce_quantita),
                    array('id' => $voce_id),
                    array('%d'),
                    array('%d')
                );

                // Verifica se il lavoro è già stato preso dall'utente corrente
                $existing_job = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM $table_name_jobs WHERE user_id = %d AND voce_id = %d",
                        $current_user_id,
                        $voce_id
                    )
                );

                if ($existing_job) {
                    // Se il lavoro è già stato preso, incrementa il conteggio
                    $new_job_count = $existing_job->count + 1;
                    $wpdb->update(
                        $table_name_jobs,
                        array('count' => $new_job_count),
                        array('id' => $existing_job->id),
                        array('%d'),
                        array('%d')
                    );
                } else {
                    // Se il lavoro non è ancora stato preso, inserisci una nuova riga
                    $wpdb->insert(
                        $table_name_jobs,
                        array(
                            'user_id' => $current_user_id,
                            'voce_id' => $voce_id,
                            'count' => 1,
                        ),
                        array('%d', '%d', '%d')
                    );
                }

                // Conferma la transazione
                $wpdb->query('COMMIT');

                // Esegui altre operazioni desiderate
                // ...

                // Redirect o messaggio di successo
                wp_redirect(home_url());
                exit;
            } catch (Exception $e) {
                // Annulla la transazione in caso di errore
                $wpdb->query('ROLLBACK');

                // Gestisci l'errore
                wp_die('Si è verificato un errore durante la transazione.');
            }
        } else {
            // Messaggio di errore
            wp_die('Non hai abbastanza gettoni o la quantità del lavoro è esaurita.');
        }
    }
}
