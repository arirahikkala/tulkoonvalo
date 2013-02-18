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

    Slider.Slider = Backbone.Model.extend({
	// Backbone uses this to figure out where to .fetch() and .save()
	urlRoot: "../server2/sliders/",
	url: function() {
	    var origUrl = Backbone.Model.prototype.url.call(this);
	    return origUrl + (origUrl.charAt(origUrl.length - 1) == '/' ? '' : '/');
	},

	defaults: function() {
	    return {
		value: 0,
		timer: 7200,
	    };
	},

    });

    Slider.SliderView = Backbone.View.extend({
	// Backbone constructs the view element (.el) with this tag and this class
	tagName: "div",
	className: "slider",
	template: _.template("<div class='slider-widget' /> <input class='timer' type='text' />"),

	// Backbone assigns these events automatically when the view is created
	events: {
	    "slidechange .slider-widget" : "updateModelFromUI",
	},

	// Backbone calls this automatically when creating the view
	initialize: function() {
	    this.model.bind("change", this.updateUIFromModel, this);
	    this.model.bind("remove", this.remove, this);
	    this.render();
	    var _this = this;
	    console.log(this.model.get('value'));
	    this.updateUIFromModel();
	},

	// self-explanatory;
	// (todo: also move over name changes to the UI)
	updateUIFromModel: function() {
	    this.$(".slider-widget").slider("value", this.model.get("value"));
	    this.$(".timer").val(this.model.get("timer"));
	},

	updateModelFromUI: function(ev, ui) {
	    this.model.set("value", this.$(".slider-widget").slider("option", "value"));
	    // if originalEvent is undefined, the event was created programmatically
	    // thus, this ensures that we don't loop
	    if (ev.originalEvent !== undefined)
		this.model.save();

	    return false;
	},
	
	// re-render the widget
	// (note: since this is a very simple view and has no subviews, it's okay to just rerender everything)
	// see http://ianstormtaylor.com/rendering-views-in-backbonejs-isnt-always-simple/ for some 
	// discussion on possible problems with larger views
	render: function () {
	    this.$el.html (this.template ({name: this.model.get('name')}));
	    this.$(".slider-widget").slider({orientation: "vertical", value: this.model.get('value')});
	    return this;
	},
	
    });
}).call(this);
