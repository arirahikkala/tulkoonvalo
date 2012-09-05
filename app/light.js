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
    Light.Light = Backbone.RelationalModel.extend({
	idAttribute: "lightid",
	urlRoot: "../server/lights/",
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

	template: _.template("<div class='light-name'><%= name %></div>\
<div class='light-widget' />"),

	events: {
	    "slidechange .light-widget" : "updateValueFromUI",
	},

	initialize: function() {
	    this.model.bind("change", this.updateValueFromModel, this);
	    this.model.bind("remove", this.remove, this);
	    this.render();
	    var _this = this;
	},

	updateValueFromModel: function() {
	    this.$(".light-widget").slider("value", this.model.get("brightness"));
	},

	render: function () {
	    this.$el.html (this.template ({name: this.model.get('name')}));
	    this.$(".light-widget").slider({orientation: "vertical", value: this.model.get('brightness')});
	    return this;
	},
	
	updateValueFromUI: function(ev, ui) {
	    this.model.set("brightness", this.$(".light-widget").slider("option", "value"));
	    // if originalEvent is undefined, the event was created programmatically
	    // check so that we don't loop
	    if (ev.originalEvent !== undefined)
		this.model.save();

	    return false;
	},
    });
}).call(this);
