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
    
 

    /* A Light has the attributes
         name (string)
	 brightness (float)

       You can access those with obj.get("name") and obj.get("brightness")

       They also have an 'id'. A canonical light's id is an integer. A light
       that's part of a program has an id of the form x_y_z, where x is the
       id of the corresponding canonical light, y that of the program line,
       and z of the program. */

    Light.Light = Backbone.RelationalModel.extend({
	// Backbone uses this to figure out where to .fetch() and .save()
	urlRoot: "../server/lights/",

	defaults: function() {
	    return {
		name: "unnamed",
		brightness: 0,
	    };
	},

    });

    Light.LightView = Backbone.View.extend({
	// Backbone constructs the view element (.el) with this tag and this class
	tagName: "div",
	className: "light",

	// Backbone assigns these events automatically when the view is created
	events: {
	    "slidechange .light-widget" : "updateModelFromUI",
	},

	// Backbone calls this automatically when creating the view
	initialize: function() {
	    this.model.bind("change", this.updateUIFromModel, this);
	    this.model.bind("remove", this.remove, this);
	    this.render();
	    var _this = this;
	},

	// self-explanatory;
	// (todo: also move over name changes to the UI)
	updateUIFromModel: function() {
	    this.$(".light-widget").slider("value", this.model.get("brightness"));
	},

	updateModelFromUI: function(ev, ui) {
	    this.model.set("brightness", this.$(".light-widget").slider("option", "value"));
	    // if originalEvent is undefined, the event was created programmatically
	    // thus, this ensures that we don't loop
	    if (ev.originalEvent !== undefined)
		this.model.save();

	    return false;
	},

	// see http://underscorejs.org/#template for documentation on the template syntax
	template: _.template("<div class='light-name'><%= name %></div>\
<div class='light-widget' />"),

	// re-render the widget
	// (note: since this is a very simple view and has no subviews, it's okay to just rerender everything)
	// see http://ianstormtaylor.com/rendering-views-in-backbonejs-isnt-always-simple/ for some 
	// discussion on possible problems with larger views
	render: function () {
	    this.$el.html (this.template ({name: this.model.get('name')}));
	    this.$(".light-widget").slider({orientation: "vertical", value: this.model.get('brightness')});
	    return this;
	},
	
    });
}).call(this);
