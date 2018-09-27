( function( $ ) {
  cleanGETParams();

  /**
   * Remove status and source GET parameters from URL
   */
  function cleanGETParams() {
    var currentURL = window.location.href;
    var URLParts = currentURL.split("?");

    if ( URLParts.length >= 2 ) {
      if ( URLParts[1].indexOf("page=wc-settings") !== -1 &&
        URLParts[1].indexOf("tab=integration") !== -1 &&
        URLParts[1].indexOf("section=nwsi") !== -1 ) {

        var URLBase           = URLParts[0];
        var GETParams         = URLParts[1].split("&");
        var filteredGETParams = "";

        var isFirst = true;
        $.each( GETParams, function(index, param) {
          if ( param.indexOf("status=") === -1 && param.indexOf("source=") === -1 ) {
            if ( isFirst ) {
              isFirst = false;
              filteredGETParams += "?";
            } else {
              filteredGETParams += "&";
            }
            filteredGETParams += param;
          }
        });

        if ( filteredGETParams.length > 0 && window.history.replaceState ) {
          window.history.replaceState( {}, null, URLBase + filteredGETParams );
        }
      }
    }
  }
  /**
   * Add new relationship after "Add" button click
   */
  $( "#nwsi-add-new-rel" ).click( function( e ) {
    e.preventDefault();

    window.location.replace( window.location.href + "&rel=new" +
      "&from=" + encodeURIComponent( $( "#nwsi-rel-from-wc" ).val() ) +
      "&from_label=" + encodeURIComponent( $("#nwsi-rel-from-wc option:selected").text().trim() ) +
      "&to=" + encodeURIComponent( $( "#nwsi-rel-to-sf" ).val() ) +
      "&to_label=" + encodeURIComponent( $( "#nwsi-rel-to-sf option:selected" ).text() )
    );

  } );

  $( document ).on( "change", ".nwsi-sf-field", function( e ) {
    if ( typeof nwsiAjax !== "object" ) {
      return;
    }

    var $selected = $( this ).find( ":selected" );
    var data = {
      action: "nwsi_get_picklist",
      // name: $selected.val(),
      // type: $selected.data( "type" ),
    }
    console.log(data);
    console.log(nwsiAjax.url);
    $.get( nwsiAjax.url, data )
    .done( function( response ) {
      console.log(response);
    } );
  } );

  $( "#nwsi-add-new-relationship" ).click( function( e ) {
    e.preventDefault();
  } );

  /**
   * Create new select and checkbox element for requires salesforce objects
   */
  $( "#nwsi-add-new-required-sf-object" ).click( function( e ) {
    e.preventDefault();

    var container = $( "#nwsi-required-sf-objects > tbody" );
    var selectNum = $( "#nwsi-required-sf-objects select" ).size();
    var defaultSelectElement = $("select[name='defaultRequiredSfObject']" );

    var output = "<tr><td>";
    // checkbox
    var newCheckbox = document.createElement( "input" );
    $( newCheckbox )
      .attr( "name", "requiredSfObjectIsActive-" + String( selectNum ) )
      .attr( "type", "checkbox" );

    output += $( newCheckbox )[0].outerHTML;
    output += "</td><td>";

    // select
    var newSelect = document.createElement( "select" );
    $( newSelect )
      .attr( "name", "requiredSfObject-" + String( selectNum ) )
      .html( defaultSelectElement.html() );

    output += $( newSelect )[0].outerHTML;
    output += "</td></tr>";

    container.append( output );

  } );

  /**
   * Create new input for unique ID
   */
  $( "#nwsi-add-new-unique-sf-field" ).click( function( e ) {
    e.preventDefault();

    var uniqueSfField0 = $( "select[name='uniqueSfField-0']" );
    var container = $( "#nwsi-unique-sf-fields" );
    var selectNum = $( "#nwsi-unique-sf-fields select" ).size();

    container.append( "<span class='nwsi-unique-fields-plus'> + </span>" );

    var newSelect = document.createElement( "select" );
    $( newSelect )
    .attr( "name", "uniqueSfField-" + String( selectNum ) )
    .html( uniqueSfField0.html() )
    .val( "none" )
    .appendTo( container );

  } );

  /**
   * Manage custom fields
   */
  $( "select[name^='wcField-']" ).change( function(e) {

    var selectedVal = $( this ).val();
    if ( selectedVal === undefined ) {
      return;
    }

    var parent = $( this ).parent();
    var name = $( this ).attr( "name" );

    if ( selectedVal.indexOf( "custom" ) > -1 ) {
      // set hidden source input
      $( "input[name='" + name + "-source']" ).val( "custom" );
      // create custom field
      if ( selectedVal === "custom-value" ) {
        var fieldType = $( "input[name='" + name + "-type']" ).val();
        var inputType = "text";
        if ( fieldType === "double" || fieldType === "integer" ) {
          inputType = "number";
        }
        parent.append( "<br/><input type='" + inputType + "' name='" + name + "-custom' />" );
      }
    } else {
      // delete custom input if user selects another option
      parent.find( "input[name='" + name + "-custom']" ).remove();
      parent.find( "br" ).remove();
    }

  } );

} )( jQuery );
