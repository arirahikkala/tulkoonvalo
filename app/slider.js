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
		brightness: 0,
	    };
	},

    });

    Slider.SliderView = Backbone.View.extend({
	tagName: "div",

	className: "slider",

	widget: {},
	$widget: {},

	template: _.template("<%= name %>\
<div class='slider-widget' />"),

	events: {
	    "slide .slider-widget" : "updateFromUI",
	},

	initialize: function() {
	    this.model.bind("change", this.updateUIFromModel, this);
	    this.render();
	},

	updateUIFromModel: function() {
	    $widget.slider("value", this.model.get("brightness"));
	},

	render: function () {
	    this.$el.html (this.template ({name: this.model.get('name')}));
	    $widget = this.$(".slider-widget");
	    widget = $widget.slider({orientation: "vertical"});

	    return this;
	},
	
	updateFromUI: function() {
	    this.model.set("brightness", $widget.slider("option", "value"));
	},
    });
}).call(this);
