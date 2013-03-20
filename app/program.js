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

	urlRoot: "../server2/programs/",	
	url: function() {
	    var origUrl = Backbone.Model.prototype.url.call(this);
	    return origUrl + (origUrl.charAt(origUrl.length - 1) == '/' ? '' : '/');
	},

	defaults: function() {
	  return {
	  	id: null,
	  	name: '',
	  	times: null,
	  	levels: null,
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
  
  Program.ProgramMainView = Backbone.View.extend ({
		tagName: 'div',
		tagClass: 'program-item',
		
			events: {
			"click #program-edit" : function (e) {
					$("#mainpage").css({"display":"none"});
					$("#programsEdit").css({"display":"block"});
					appRouter.navigate('programsEdit/'+this.model.id, {trigger: true, replace: true});
				}
			},
			
		initialize: function() {
				this.model.bind ("remove", this.remove, this);
				this.model.bind ("change:name", this.render, this);
				this.render();
		},
		
		template: _.template("<tr>\
		<td id='program-name'></td>\
		<td><input id='program-edit' type=button value='Muokkaa'></td>\
		<tr>"),
	
		render: function() {
			this.$el.html (this.template())
			this.$("#program-name").html(this.model.get("name"));
			return this;
		},
  })

}).call(this);
