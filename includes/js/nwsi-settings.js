( function( $ ) {
  /**
   * Add new relationship after "Add" button click
   */
  $( "#nwsi-add-new-rel" ).click( function( e ) {
    e.preventDefault();

    window.location.replace( window.location.href + "&rel=new" +
      "&from=" + encodeURIComponent( $( "#nwsi-rel-from-wc" ).val() ) +
      "&from_label=" + encodeURIComponent( $("#nwsi-rel-from-wc option:selected").text() ) +
      "&to=" + encodeURIComponent( $( "#nwsi-rel-to-sf" ).val() ) +
      "&to_label=" + encodeURIComponent( $( "#nwsi-rel-to-sf option:selected" ).text() )
    );

  } );

  /**
   * Create new select and checkbox element for requires salesforce objects
   */
  $( "#nwsi-add-new-required-sf-object" ).click( function(e) {
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

    var newSelect = document.createElement("select");
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
