(function() {
    var root = this;
    var Light = {};

    // export the name Light, either using CommonJS modules or just assigning
    // it to the root object directly; copied from underscore.js
    if (typeof exports !== 'undefined') {
	if (typeof module !== 'undefined' && module.exports) {
	    exports = module.exports = Light;
	}
	exports.Light = Light;
    } else {
	root['Light'] = Light;
    }
    
 

    // attributes: name (string), brightness (float)
    Light.Light = Backbone.Model.extend({
	defaults: function() {
	    return {
		name: "unnamed",
		brightness: 0,
	    };
	},

    });

    Light.LightView = Backbone.View.extend({
	tagName: "div",

	className: "light",

	widget: {},
	$widget: {},

	template: _.template("<%= name %>\
<div class='light-widget' />"),

	events: {
	    "slide .light-widget" : "updateValueFromUI",
	},

	initialize: function() {
	    this.model.bind("change", this.updateValueFromModel, this);
	    this.model.bind("remove", this.remove, this);
	    this.render();
	},

	updateValueFromModel: function() {
	    $widget.slider("value", this.model.get("brightness"));
	},

	render: function () {
	    this.$el.html (this.template ({name: this.model.get('name')}));
	    $widget = this.$(".light-widget");
	    widget = $widget.slider({orientation: "vertical", value: this.model.get('brightness')});

	    return this;
	},
	
	updateValueFromUI: function() {
	    this.model.set("brightness", $widget.light("option", "value"));
	},
    });
}).call(this);
