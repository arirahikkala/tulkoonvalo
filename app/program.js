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

    Program.SliderList = Backbone.Collection.extend ({
	url: function() { 
	    return "/programs/"+this.parent.get("id")+"/sliders/";
	}
    });

    // attributes: sliders (array of Slider), name (string)
    Program.Program = Backbone.Model.extend ({
	defaults: function() {
	    return {
		sliders: new Program.SliderList([], { parent: this }),
		name: "unnamed program"
	    };
	},

	addSlider: function() {
	    
	},
    });
    
    // an expanded view of a program, with all content shown
    Program.ProgramView = Backbone.View.extend ({
	tagName: "div",

	className: "program",

	addSlider: function(slider) {
	    var view = new Slider.SliderView({model: slider});
	    this.$(".program").append(view.render().el);
	},

	initialize: function() {
	    this.model.bind ("change", this.render, this);
	    this.model.bind ("add", this.addSlider, this);
	    this.model.bind ("remove", this.remove, this);
	    this.render();
	},

	render: function() {
	    this.$el.html ("<div class='program' />");
	    _.each(this.model.get("sliders"), function (x) { addSlider (x)});
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
