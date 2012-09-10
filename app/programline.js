(function() {
    var root = this;
    var ProgramLine = {};

    if (typeof exports !== 'undefined') {
	if (typeof module !== 'undefined' && module.exports) {
	    exports = module.exports = Programline;
	}
	exports.ProgramLine = ProgramLine;
    } else {
	root['ProgramLine'] = ProgramLine;
    }

    // attributes: lights (array of Light), 
    ProgramLine.ProgramLine = Backbone.RelationalModel.extend ({
	defaults: function() {
	    return {
//		lights: new ProgramLine.LightList([], { parent: this }),
	    };
	},

	idAttribute: "lineid",

	initialize: function (attrs) {
	    this.urlRoot = attrs.urlRoot;
	},

	relations: [{
	    type: Backbone.HasMany,
	    key: 'lights',
	    relatedModel: 'Light.Light',
	}],

	url: function() {
            var origUrl = Backbone.Model.prototype.url.call(this);
            return origUrl + (origUrl.charAt(origUrl.length - 1) == '/' ? '' : '/');
	}

    });
    
    // an expanded view of a programline, with all content shown
    ProgramLine.ProgramLineView = Backbone.View.extend ({
	tagName: "div",

	className: "programline",

	addLight: function(light) {
	    console.log ("addlight");
	    var view = new Light.LightView({model: light});
	    this.$el.append(view.render().el);
	},

	initialize: function() {
	    this.model.bind ("update:lights", this.render, this);
	    this.model.bind ("add:lights", this.addLight, this);
	    this.model.bind ("remove:lights", this.remove, this);
	    this.render();
	},

	render: function() {
	    _.each(this.model.get("lights"), function (x) { addLight (x)});
	}
    })


}).call(this);
