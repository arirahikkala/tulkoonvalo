(function() {
    var root = this;
    var Program = {};

    if (typeof exports !== 'undefined') {
	if (typeof module !== 'undefined' && module.exports) {
	    exports = module.exports = Program;
	}
	exports.Program = Program;
    } else {
	root['Program'] = Program;
    }

    Program.LightList = Backbone.Collection.extend ({
	url: function() { 
	    return "/programs/"+this.parent.get("id")+"/lights/";
	}
    });

    // attributes: lights (array of Light), name (string)
    Program.Program = Backbone.Model.extend ({
	defaults: function() {
	    return {
		lights: new Program.LightList([], { parent: this }),
		name: "unnamed program"
	    };
	},

	addLight: function() {
	    
	},
    });
    
    // an expanded view of a program, with all content shown
    Program.ProgramView = Backbone.View.extend ({
	tagName: "div",

	className: "program",

	addLight: function(light) {
	    var view = new Light.LightView({model: light});
	    this.$(".program").append(view.render().el);
	},

	initialize: function() {
	    this.model.bind ("change", this.render, this);
	    this.model.bind ("add", this.addLight, this);
	    this.model.bind ("remove", this.remove, this);
	    this.render();
	},

	render: function() {
	    this.$el.html ("<div class='program' />");
	    _.each(this.model.get("lights"), function (x) { addLight (x)});
	}
    })


    // a view of a program as a list item, i.e. just the name
    Program.ItemView = Backbone.View.extend ({
	el: 'span',

	initialize: function() {
	    this.model.bind ("remove", this.remove, this);
	    this.model.bind ("change:name", this.render, this);
	    this.render();
	},

	render: function() {
//	    console.log (this.model);
	    this.$el.html ("<span class='program-item'>" + this.model.get("name") + "</span>");
	    return this;
	}
    })
}).call(this);
