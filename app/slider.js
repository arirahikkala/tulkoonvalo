(function() {
    var root = this;
    var Slider = {};

    // export the name Slider, either using CommonJS modules or just assigning
    // it to the root object directly; copied from underscore.js
    if (typeof exports !== 'undefined') {
	if (typeof module !== 'undefined' && module.exports) {
	    exports = module.exports = Slider;
	}
	exports.Slider = Slider;
    } else {
	root['Slider'] = Slider;
    }
    
 

    // attributes: name (string), brightness (float)
    Slider.Slider = Backbone.Model.extend({
	defaults: function() {
	    return {
		name: "unnamed",
		brightness: 0
	    };
	}
    });

    Slider.SliderView = Backbone.View.extend({
	tagName: "div",

	className: "slider",

	template: _.template("<div class='slider'>\
<%= name %>\
<div class='slider-widget' id='<%= id %>' />\
</div>"),

	events: {
	    "change .slider-widget" : "updateFromUI",
	    "slide .slider-widget" : "updateFromUI"
	},

	render: function () {
	    this.$el.html = "test";
	    return this;
	}
    });
}).call(this);

/*
    sliderObject.on("change:brightness", function() { $("#outputnum").html (sliderObject.get("brightness"));});

    $(function() {
	var slider = $( "#slider" ).slider({
	    orientation: "vertical",
	    min: 0,
	    max: 1,
	    step: 0.001,
	    change: function(event, ui) {sliderObject.set ({ brightness: $("#slider").slider("option", "value")});}
	});
*/



// move into sliderlist or something?
/*    // attributes: sliders (array of Slider)
    var Program = Backbone.Model.extend({
	defaults: function() {
	    return {
		sliders: Array()
	    }
	},

	getNumSliders: function() { return this.get("sliders").length; },

	addSlider: function(newslider) { 
	    var s = this.get("sliders");
	    s.push (newslider);
	    this.set (s);
	}
    });
*/

