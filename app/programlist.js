(function() {
    var root = this;
    var ProgramList = {};

    if (typeof exports !== 'undefined') {
	if (typeof module !== 'undefined' && module.exports) {
	    exports = module.exports = ProgramList;
	}
	exports.ProgramList = ProgramList;
    } else {
	root['ProgramList'] = ProgramList;
    }

    ProgramList.ProgramList = Backbone.Collection.extend ({
	// by default program lists are stored in alphabetical order by name
	// (should this be a view detail?)
//	comparator: function (model) {
//	    return model.get("name");
//	}
    });

    // a program item, as an element in a multiple choice menu
    // not exported!
    MultiChoiceMenuItem = Backbone.View.extend ({
	initialize: function (program) {
	    var view = new Program.ItemView ({model: program});
	    var item_el = view.render().el;
	    var check = $("<input class='toggle' type='checkbox' />");
	    this.$el.html ("<div>" + item_el + check + "</div>");
	}
    })

    ProgramList.MultipleChoiceMenu = Backbone.View.extend ({
	addProgramItem: function (program) {
	    var item = new MultiChoiceMenuItem ({model: program});
	    var item_el = item.render().el;
	    this.$(".program-list").append(item_el);
	},

	initialize: function () {
	    var _this = this;
	    this.model.bind ("change", function () { _this.render(); });
	    this.render();
	},

	render: function() {
	    this.$el.html ("<div class='program-list' />");
	    var _this = this;
	    _this.model.each (function (x) { _this.addProgramItem (x)});
	}
    });

    ProgramList.SingleChoiceMenu = Backbone.View.extend ({
	
    });

}).call(this);
