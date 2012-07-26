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
	    return this.parent.get("id")+"/sliders/";
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
	    
	}
    });
    
    // an expanded view of a program, with all content shown
    Program.ProgramView = Backbone.View.extend ({
	tagName: "div",

	className: "program",

	addSlider: function(slider) {
	    alert ("hi");
	    var view = new Slider.SliderView({model: slider});
	    this.$(".program").append(view.render().el);
	},

	initialize: function() {
	    this.$el.html ("<div class='program' />");
	    _.each(this.model.get("sliders"), function (x) { addSlider (x)});
	},

	render: function() {
	    
	}
    })


    // a view of a program as a list item, i.e. just the name
    Program.ItemView = Backbone.View.extend ({
	initialize: function() {
	    this.render();
	},

	render: function() {
//	    console.log (this.model);
	    this.$el.html ("<div class='program-item'>" + this.model.model.get("name") + "</div>");
	    return this;
	}
    })
}).call(this);
