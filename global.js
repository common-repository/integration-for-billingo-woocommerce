jQuery(document).ready(function($) {
  $('#wc_billingo_generate').click(function(e) {
    e.preventDefault();
    var r = confirm("Biztosan létrehozod a számlát?");
    if (r != true) {
        return false;
    }
    var nonce = $(this).data('nonce');
    var order = $(this).data('order');
    var button = $('#wc-billingo-generate-button');
    var note = $('#wc_billingo_invoice_note').val();
    var deadline = $('#wc_billingo_invoice_deadline').val();
    var completed = $('#wc_billingo_invoice_completed').val();
    var request = $('#wc_billingo_invoice_request').is(':checked');
     if(request) {
      request = 'on';
    } else {
      request = 'off';
    }

    var data = {
        action: 'wc_billingo_generate_invoice',
        nonce: nonce,
        order: order,
        note: note,
        deadline: deadline,
        completed: completed,
        request: request
    };

    button.block({message: null, overlayCSS: {background: '#fff url(' + wc_billingo_params.loading + ') no-repeat center', backgroundSize: '16px 16px', opacity: 0.6}});

    $.post( ajaxurl, data, function( response ) {
      //Remove old messages
      $('.wc-billingo-message').remove();

      var responseText = response;

      console.log(responseText);

      //Generate the error/success messages
      if(responseText.data.error) {
        button.before('<div class="wc-billingo-error error wc-billingo-message"></div>');
      } else {
        button.before('<div class="wc-billingo-success updated wc-billingo-message"></div>');
      }

      //Get the error messages
      var ul = $('<ul>');
      $.each(responseText.data.messages, function(i,value){
        var li = $('<li>')
        li.append(value);
        ul.append(li);
      });
      $('.wc-billingo-message').append(ul);

      //If success, hide the button
      if(!responseText.data.error) {
        button.slideUp();
        button.before(responseText.data.link);
      }

      button.unblock();
    });
  });

  $('#wc_billingo_options').click(function(){
    $('#wc_billingo_options_form').slideToggle();
    return false;
  });

  $('#wc_billingo_already').click(function(e) {
    e.preventDefault();
    var note = prompt("Számlakészítés kikapcsolása. Mi az indok?", "Ehhez a rendeléshez nem kell számla.");
    if (!note) {
      return false;
    }

    var nonce = $(this).data('nonce');
    var order = $(this).data('order');
    var button = $('#wc-billingo-generate-button');

    var data = {
        action: 'wc_billingo_already',
        nonce: nonce,
        order: order,
        note: note
    };

    button.block({message: null, overlayCSS: {background: '#fff url(' + wc_billingo_params.loading + ') no-repeat center', backgroundSize: '16px 16px', opacity: 0.6}});

    $.post( ajaxurl, data, function( response ) {
      //Remove old messages
      $('.wc-billingo-message').remove();

      var responseText = response;

      //Generate the error/success messages
      if(responseText.data.error) {
        button.before('<div class="wc-billingo-error error wc-billingo-message"></div>');
      } else {
        button.before('<div class="wc-billingo-success updated wc-billingo-message"></div>');
      }

      //Get the error messages
      var ul = $('<ul>');
      $.each(responseText.data.messages, function(i,value){
        var li = $('<li>')
        li.append(value);
        ul.append(li);
      });
      $('.wc-billingo-message').append(ul);

      //If success, hide the button
      if(!responseText.data.error) {
        button.slideUp();
        button.before(responseText.data.link);
      }

      button.unblock();
    });
  });

  $('#wc_billingo_already_back').click(function(e) {
    e.preventDefault();
    var r = confirm("Biztosan visszakapcsolod a számlakészítés ennél a rendelésnél?");
    if (r != true) {
        return false;
    }

    var nonce = $(this).data('nonce');
    var order = $(this).data('order');
    var button = $('#wc-billingo-generate-button');

    var data = {
        action: 'wc_billingo_already_back',
        nonce: nonce,
        order: order
    };

    $('#billingo_already_div').block({message: null, overlayCSS: {background: '#fff url(' + wc_billingo_params.loading + ') no-repeat center', backgroundSize: '16px 16px', opacity: 0.6}});

    $.post( ajaxurl, data, function( response ) {
      //Remove old messages
      $('.wc-billingo-message').remove();

      var responseText = response;

      //Generate the error/success messages
      if(responseText.data.error) {
        button.before('<div class="wc-billingo-error error wc-billingo-message"></div>');
      } else {
        button.before('<div class="wc-billingo-success updated wc-billingo-message"></div>');
      }

      //Get the error messages
      var ul = $('<ul>');
      $.each(responseText.data.messages, function(i,value){
        var li = $('<li>')
        li.append(value);
        ul.append(li);
      });
      $('.wc-billingo-message').append(ul);

      //If success, show the button
      if(!responseText.data.error) {
        button.slideDown();
      }

      $('#billingo_already_div').unblock().slideUp();
    });
  });
});
