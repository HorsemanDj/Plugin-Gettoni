jQuery(document).ready(function($) {
    $('.gettoni-plugin-prendi-lavoro').click(function() {
      var voceId = $(this).data('voce-id');
      
      $.ajax({
        url: gettoni_plugin_ajax_object.ajax_url,
        type: 'POST',
        data: {
            action: 'prende_lavoro',
            voce_id: voceId,
            security: gettoni_plugin_ajax_object.nonce
        },
        success: function(response) {
          // Gestisci la risposta dal server
          if (response.success) {
            // Se l'azione è stata eseguita con successo, aggiorna la pagina o mostra un messaggio di successo
            alert(response.data);
            location.reload(); // Esempio: ricarica la pagina per mostrare l'elenco delle voci aggiornato
          } else {
            // Se l'azione non può essere eseguita, mostra un messaggio di errore
            alert(response.data);
          }
        },
        error: function(xhr, status, error) {
          // Gestisci gli errori della richiesta AJAX
          console.log(error);
        }
      });
    });
  });
  