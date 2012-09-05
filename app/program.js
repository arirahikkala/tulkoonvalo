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

    Program.Program = Backbone.RelationalModel.extend ({
	idAttribute: 'programid',

	relations: [{
	    type: Backbone.HasMany,
	    key: 'lines',
	    relatedModel: 'ProgramLine.ProgramLine',
	}],


    });

    // a view of a program as a list item, i.e. just the name
    Program.ItemView = Backbone.View.extend ({
	tagName: 'span',
	tagClass: 'program-item',

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
