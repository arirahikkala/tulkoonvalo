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
	//idAttribute: 'programid',

	/*
	relations: [{
	    type: Backbone.HasMany,
	    key: 'lines',
	    relatedModel: 'ProgramLine.ProgramLine',
	}],
	*/

	defaults: function() {
	  return {
	  	id: null,
	  	name: null,
	  	target_id: null,
	  	light_detector: null,
	  	motion_detector: null,
	  	light_level: null,
	  	motion_level: null,
	  	times: null,
	  	levels: null,
	  	saved: true,
	  }
	},

	});

    // a view of a program as a list item, i.e. just the name
    Program.ProgramView = Backbone.View.extend ({
	tagName: 'span',
	tagClass: 'program-item',

	initialize: function() {
	    this.model.bind ("remove", this.remove, this);
	    this.model.bind ("change:name", this.render, this);
	    this.render();
	},
	
	template: _.template("<span class='programItem' />\
	<div>Ma<input class='program_day' id='mon' type='checkbox'>\
	Ti<input class='program-day' id='tue' type='checkbox'>\
	Ke<input class='program-day' id='wed' type='checkbox'>\
	To<input class='program-day' id='thu' type='checkbox'>\
	Pe<input class='program-day' id='fri' type='checkbox'>\
	La<input class='program-day' id='sat' type='checkbox'>\
	Su<input class='program-day' id='sun' type='checkbox'></div>\
	Voimassa klo.<input class='program-time' id='start'>-<input class='program-time' id='end'></div>\
	<div class='program-sliders'><div class='program-slider' id='motion' />\
	<div class='program-slider' id='sun' /></div>"),

	render: function() {
	    this.$el.html (this.template() );
	    this.$(".program-slider").slider({orientation: "vertical", value: this.model.get('value')});
 	    this.$(".program-slider").slider({orientation: "vertical", value: this.model.get('value')});

	    return this;
	},
	
  })
    
}).call(this);
