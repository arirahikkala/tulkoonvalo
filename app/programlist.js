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
	url: "../server/programs/",
	model: Program.Program
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
	    opts.model.on ("change", function (opts) { this.renderWithOpts(opts) } );
	    this.renderWithOpts (opts);
	},

	renderWithOpts: function (opts) {
	    var view = new Program.ItemView ({model: opts.model});
	    var item_el = view.render().$el;
	    var check;
	    if (opts.isSelected)
		check = "<input class='toggle' checked='checked' type='checkbox';'/>";
	    else
		check = "<input class='toggle' type='checkbox';'/>";
	    this.$el.html ("<div>" + item_el.html() + check + "</div>");
	}
    });

    ProgramList.SingleChoiceMenuItem = Backbone.View.extend ({
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
	    opts.model.on ("change", function (opts) { this.renderWithOpts (opts)});
	    this.renderWithOpts (opts);
	},

	// yes, using the names like that is okay (as long as there's only one menu like this in the document)
	renderWithOpts: function (opts) {
	    var radio;
	    if (opts.isSelected)
		check = "<input class='radio' name='singlechoicemenu' checked='checked' type='checkbox';'/>";
	    else
		check = "<input class='radio' name='singlechoicemenu' type='checkbox';'/>";
	    var view = new Program.ItemView ({model: opts.model});
	    this.$el.html (view.render().el);
	}
    });

    ProgramList.MultipleChoiceMenu = Backbone.View.extend ({
	tagName: 'div',
	className: 'program-list',

	selected: {},

	addProgramItem: function (program) {
	    var item = new MultiChoiceMenuItem ({model: program, container: this, isSelected: program.cid in this.selected});
	    var item_el = item.render().el;
	    this.$el.append(item_el);

	},

	initialize: function () {
	    this.model.bind ("add", this.render, this);
	    this.model.bind ("remove", this.render, this);
	    // todo: figure out a way to bind changes to programs in the menuitem constructor rather than here
	    // (so that the entire menu won't need to be rerendered for any change to a program)
	    this.model.bind ("update", this.render, this);
	    this.render();
	},

	render: function() {
	    this.$el.empty();
	    var _this = this;
	    _this.model.each (function (x) { _this.addProgramItem (x)});
	},

    });

    ProgramList.SingleChoiceMenu = Backbone.View.extend ({
	tagName: 'div',
	className: 'program-list',

	selected: {},

	addProgramItem: function (program) {
	    var item = new ProgramList.SingleChoiceMenuItem ({model: program, container: this, isSelected: program.cid in this.selected});
	    var item_el = item.render().el;
	    this.$el.append(item_el);
	    console.log (item_el);

	},

	initialize: function () {
	    this.model.bind ("add", this.render, this);
	    this.model.bind ("remove", this.render, this);
	    // todo: figure out a way to bind changes to programs in the menuitem constructor rather than here
	    // (so that the entire menu won't need to be rerendered for any change to a program)
	    this.model.bind ("change", this.render, this);

        $("#lightsList").bind (

            "select_node.jstree", function(evt, data) {
                //selected node object to data object: data.inst.get_json()[0];
              //  console.log(data.inst.get_text());
                  this.itemSelected();
            }

        );

	    this.render();
	},

	render: function() {
	    this.$el.empty();
	    var _this = this;
	    _this.model.each (function (x) { _this.addProgramItem (x)});
	},
    });

}).call(this);
