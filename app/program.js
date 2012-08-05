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

    Program.ProgramLineList = Backbone.Collection.extend ({
	url: function() { 
	    return "/programs/"+this.get("id")+"/lines/";
	}
    });

    Program.Program = Backbone.Model.extend ({
	
    });

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
	    this.$el.html ("<span class='programItem'>" + this.model.get("name") + "</span>");
	    return this;
	}
    })
    
}).call(this);
