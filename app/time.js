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
	  }
	},
	
	});

  Time.TimeView = Backbone.View.extend ({
	tagName: "div",
	className: "time-item",

	events: {
		// A weekday is clicked
		"click .program-day" : function (event) {
			
			var weekdays = this.model.get("weekdays");
			var index = parseInt(event.target.id);
			
			if (event.target.checked)
				weekdays = weekdays.substr(0, index) + "1" + weekdays.substr(index+1);
			else
				weekdays = weekdays.substr(0, index) + "0" + weekdays.substr(index+1);

			this.model.set("weekdays", weekdays);
		},
		// Time is changed
		"change #time-start" : function (event) { this.model.set("time_start", event.target.value); },
		"change #time-end" : function (event) { this.model.set("time_end", event.target.value); },
	},

	initialize: function() {
			// Convert weekday binary into 10-base
			//this.model.set("weekdays", parseInt(this.model.get("weekdays"), 2));
			
	    this.model.bind ("remove", this.remove, this);
	    this.model.bind ("change:name", this.render, this);
	    this.render();
	    
	    // Used for showing error messages in the right place
	    this.model.set("cid", this.model.cid);
	},
	template: _.template("\
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
		<input class='program-time' id='time-start'>-\
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
