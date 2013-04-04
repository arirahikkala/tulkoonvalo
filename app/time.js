(function() {
    var root = this;
    var Time = {};

    if (typeof exports !== 'undefined') {
	if (typeof module !== 'undefined' && module.exports) {
	    exports = module.exports = Time;
	}
	exports.Time = Time;
    } else {
	root['Time'] = Time;
    }

    Time.Time = Backbone.RelationalModel.extend ({

	defaults: function() {
	  return {
	  	id: null,
	  	cid: null,
	  	date_start: '',
	  	date_end: '',
	  	weekdays: '0000000',
	  	time_start: '',
	  	time_end: '',
	  	new_time: true,
	  	allow_delete: false,
	  }
	},
	
	});

  Time.TimeView = Backbone.View.extend ({
	tagName: "div",
	className: "time-item",

	events: {
		// A weekday is clicked
		"click .program-day" : function(event) {
			
			var weekdays = this.model.get("weekdays");
			var index = parseInt(event.target.id);
			
			if (event.target.checked)
				weekdays = weekdays.substr(0, index) + "1" + weekdays.substr(index+1);
			else
				weekdays = weekdays.substr(0, index) + "0" + weekdays.substr(index+1);

			this.model.set("weekdays", weekdays);
		},
		
		"click #time-item-remove" : function() {
			if (! this.model.get("new_time"))  {
				var choice = confirm("Haluatko varmasti poistaa ajan?");
				if (choice) {
					this.model.set("allow_delete", true)			
					this.remove();
				}
			}
			else { this.model.destroy(); this.remove(); }
		},
			
		// Time is changed
		"change #time-start" : function (event) { this.model.set("time_start", event.target.value); },
		"change #time-end" : function (event) { this.model.set("time_end", event.target.value); },
	},

	initialize: function() {
			this.model.bind("change:errors", function() { this.drawErrors(); }, this);
   		this.model.bind("remove", function() { this.remove(); }, this);

	    this.render();
	    
	    // Used for showing error messages in the right place
	    this.model.set("cid", this.model.cid);
	},
	
	drawErrors: function() {
		var errors = this.model.get("errors");
		if (errors) {
			for (var i in errors)
				this.$("#programsErrorTime").append(this.model.collection.getErrorMessage(errors[i])+"<br />");
		}
		else
			this.$("#programsErrorTime").html("");
	},
	
	// TODO: Time picker license
	template: _.template("<div class='programError' id='programsErrorTime'></div>\
	<input id='time-item-remove' type='button' value='Poista aika'>\
	<div id='program-days'>\
		Ma<input class='program-day' id='0' type='checkbox'>\
		Ti<input class='program-day' id='1' type='checkbox'>\
		Ke<input class='program-day' id='2' type='checkbox'>\
		To<input class='program-day' id='3' type='checkbox'>\
		Pe<input class='program-day' id='4' type='checkbox'>\
		La<input class='program-day' id='5' type='checkbox'>\
		Su<input class='program-day' id='6' type='checkbox'>\
	</div>\
	<div class='programs-time'>Voimassa klo.\
		<input class='program-time' id='time-start'> -\
		<input class='program-time' id='time-end'>\
	</div>"),
	
	render: function() {
		this.$el.html(this.template());
		
		// Fill in the weekday checkboxes
		var weekdays = this.model.get("weekdays");
		var counter = 0;
		
		while (counter < 7) {
			curDay = this.$("#program-days").children().get(counter);
			
			if (weekdays.length < counter+1) {
				curDay.checked = false;
			}
			else {
				if (weekdays[counter] == "1")
					curDay.checked = true;
			}
			counter++;
		}
		
		// Fill in other time information
		this.$("#time-start").val(this.model.get("time_start"));
		this.$("#time-end").val(this.model.get("time_end"));
		
		return this;
	},
	
  })
    
}).call(this);
