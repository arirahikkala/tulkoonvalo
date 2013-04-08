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
			//this.model.on("remove", function() {console.log("removexxxx"); this.remove() }, this);
		},
		
		template: _.template("<tr>\
		<td id='program-name'></td>\
		<td><input id='program-edit' type=button value='Muokkaa'></td>\
		<td><input id='program-delete' type=button value='Poista'></td>\
		<tr>"),
	
		render: function() {
			this.$el.html(this.template())
			this.$("#program-name").append(this.model.get("name"));
			return this;
		},
  })

}).call(this);
