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
	events: {
	    "change .toggle" : function() { this.updateContainer(); },
	},

	updateContainer: function () {
	    if (this.$(".toggle").attr("checked")) {
		this.container.selected[this.cid] = true;
	    } else {
		delete (this.container.selected[this.cid]);
	    }
	},

	initialize: function (opts) {
	    this.cid = opts.model.cid;
	    this.container = opts.container;
	    opts.model.on ("change", this.renderWithOpts(opts));
	    this.renderWithOpts (opts);
	},

	renderWithOpts: function (opts) {
	    console.log ("hi " + this.cid + ", new name: " + opts.model.get("name"));
	    var view = new Program.ItemView ({model: opts.model});
	    var item_el = view.render().$el;
	    var check;
	    if (opts.isSelected)
		check = "<input class='toggle' checked='checked' type='checkbox';'/>";
	    else
		check = "<input class='toggle' type='checkbox';'/>";

	    this.$el.html ("<div>" + item_el.html() + check + "</div>");
	}
    })

    ProgramList.MultipleChoiceMenu = Backbone.View.extend ({
	selected: {},

	addProgramItem: function (program) {
	    var item = new MultiChoiceMenuItem ({model: program, container: this, isSelected: program.cid in this.selected});
	    var item_el = item.render().el;

	    this.$(".program-list").append(item_el);
	    
	},

	initialize: function () {
	    var _this = this;
	    this.model.bind ("add", function () { _this.render(); });
	    this.model.bind ("remove", function () { _this.render(); });
	    // todo: figure out a way to bind changes to programs in the menuitem constructor rather than here
	    this.model.bind ("change", function () { _this.render(); });
	    this.render();
	},

	render: function() {
	    this.$el.html ("<div class='program-list' />");
	    var _this = this;
	    _this.model.each (function (x) { _this.addProgramItem (x)});
	},

    });

    ProgramList.SingleChoiceMenu = Backbone.View.extend ({
	
    });

}).call(this);
