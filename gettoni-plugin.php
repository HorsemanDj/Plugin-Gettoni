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
        quantita_disponibile int(11) NOT NULL,
        user_id int(11) NOT NULL DEFAULT 0,
        count int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
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
    add_menu_page(
        'Gettoni', // Titolo della voce di menu
        'Gettoni', // Etichetta del menu
        'manage_options',
        'gettoni-plugin-menu', // Slug del menu
        'gettoni_plugin_dashboard_page', // Callback per la pagina
        'dashicons-money-alt', // Icona del menu (puoi scegliere un'icona da https://developer.wordpress.org/resource/dashicons/)
        2 // Posizione del menu nel menu di amministrazione
    );

    add_submenu_page(
        'gettoni-plugin-menu', // Slug del menu padre
        'Aggiungi Gettoni', // Titolo della sottovoce
        'Aggiungi Gettoni', // Etichetta della sottovoce
        'manage_options',
        'gettoni-plugin-add-gettoni', // Slug della sottovoce
        'gettoni_plugin_add_gettoni_page' // Callback per la pagina
    );

    add_submenu_page(
        'gettoni-plugin-menu', // Slug del menu padre
        'Voci', // Titolo della sottovoce
        'Voci', // Etichetta della sottovoce
        'manage_options',
        'gettoni-plugin-voci', // Slug della sottovoce
        'gettoni_plugin_voci_page' // Callback per la pagina
    );
}

// Callback per la pagina di dashboard
function gettoni_plugin_dashboard_page() {
    $users = get_users();

    echo '<div class="wrap">';
    echo '<h1>Dashboard</h1>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>Nome Utente</th><th>Gettone</th><th>Lavori Presi</th></tr></thead>';
    echo '<tbody>';

    foreach ($users as $user) {
        $gettone = get_user_meta($user->ID, 'gettone', true);

        if ($gettone > 0) {
            echo '<tr>';
            echo '<td>' . $user->user_login . '</td>';
            echo '<td>' . $gettone . '</td>';
            // Recupera i lavori presi dall'utente corrente
            global $wpdb;
            $table_name = $wpdb->prefix . 'gettoni_voci';
            $taken_jobs = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d", $user->ID));

            echo '<td>';
            if ($taken_jobs) {
                foreach ($taken_jobs as $job) {
                    echo $job->lavoro . '<br>';
                }
            } else {
                echo 'Nessun lavoro preso';
            }
            echo '</td>';

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
function gettoni_plugin_prende_lavoro() {
    if (isset($_POST['prendi_lavoro'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gettoni_voci';
        $voce_id = intval($_POST['voce_id']);

        // Verifica se l'utente ha abbastanza gettoni e se la quantità è maggiore di 0
        $current_user_id = get_current_user_id();
        $user_gettone = get_user_meta($current_user_id, 'gettone', true);

        $voce = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $voce_id));
        $voce_quantita = $voce->quantita;
        $voce_quantita = $voce->quantita;
        $voce_user_id = $voce->user_id;

        if ($user_gettone > 0 && $voce_quantita > 0) {
            // Aggiorna il numero di gettoni dell'utente
            $new_user_gettone = $user_gettone - 1;
            update_user_meta($current_user_id, 'gettone', $new_user_gettone);

            // Aggiorna la quantità disponibile del lavoro
            $new_voce_quantita = $voce_quantita - 1;
            $wpdb->update(
                $table_name,
                array('quantita' => $new_voce_quantita),
                array('id' => $voce_id),
                array('%d'),
                array('%d')
            );

            // Verifica se il lavoro ha già un possessore
            if ($voce_user_id == 0) {
                // Se il lavoro non ha un possessore, assegna l'ID dell'utente corrente al lavoro
                $wpdb->update(
                    $table_name,
                    array('user_id' => $current_user_id),
                    array('id' => $voce_id),
                    array('%d'),
                    array('%d')
                );
            }

            // Incrementa il conteggio per il lavoro
            $wpdb->query($wpdb->prepare("UPDATE $table_name SET count = count + 1 WHERE id = %d", $voce_id));

            // Esegui altre operazioni desiderate
            // ...

            // Redirect o messaggio di successo
            wp_redirect(home_url());
            exit;
        } else {
            // Messaggio di errore
            wp_die('Non hai abbastanza gettoni o la quantità del lavoro è esaurita.');
        }
    }
}
