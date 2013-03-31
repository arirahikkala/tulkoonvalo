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

	urlRoot: "../server2/programs/",	
	url: function() {
	    var origUrl = Backbone.Model.prototype.url.call(this);
	    return origUrl;
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
				this.render();
		},
		
		template: _.template("<div>Ma<input class='program_day' id='mon' type='checkbox'>\
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
				},
				
			"click #program-delete": function(e) {
					var choice = confirm("Haluatko varmasti poistaa säännön '"+this.model.get("name")+"'?");
					console.log(choice);
					if (choice) {
						this.model.destroy();
						console.log(this.model.get("times"));
					}
				}
			},
			
		initialize: function() {
				this.render();
		},
		
		template: _.template("<tr>\
		<td id='program-name'></td>\
		<td><input id='program-edit' type=button value='Muokkaa'></td>\
		<td><input id='program-delete' type=button value='Poista'></td>\
		<tr>"),
	
		render: function() {
			this.$el.html (this.template())
			this.$("#program-name").html(this.model.get("name"));
			return this;
		},
  })

}).call(this);
