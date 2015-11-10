jQueryExaport(function($){
	$(function() {
	    var select = $( "#id_itemgrade" );
	    var slider = $( "<div id='slider'></div>" ).insertAfter( select ).slider({
	      min: 0,
	      max: 100,
	      range: "min",
	      value: select[ 0 ].selectedIndex + 1,
	      slide: function( event, ui ) {
	        select[ 0 ].selectedIndex = ui.value - 1;
	      }
	    });
	    $( "#id_itemgrade" ).change(function() {
	      slider.slider( "value", this.selectedIndex + 1 );
	    });
	  });
	
});