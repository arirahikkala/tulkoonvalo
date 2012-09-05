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

    ProgramLine.LightList = Backbone.Collection.extend ({
	url: function() { 
	    return "/programs/"+this.parent.get("id")+"/lights/";
	}
    });

    // attributes: lights (array of Light), name (string)
    ProgramLine.ProgramLine = Backbone.Model.extend ({
	defaults: function() {
	    return {
		lights: new ProgramLine.LightList([], { parent: this }),
		name: "unnamed programline"
	    };
	},

	addLight: function() {
	    
	},
    });
    
    // an expanded view of a programline, with all content shown
    ProgramLine.ProgramLineView = Backbone.View.extend ({
	tagName: "div",

	className: "programline",

	addLight: function(light) {
	    var view = new Light.LightView({model: light});
	    this.$el.append(view.render().el);
	},

	initialize: function() {
	    this.model.bind ("change", this.render, this);
	    this.model.bind ("add", this.addLight, this);
	    this.model.bind ("remove", this.remove, this);
	    this.render();
	},

	render: function() {
	    _.each(this.model.get("lights"), function (x) { addLight (x)});
	}
    })


}).call(this);
