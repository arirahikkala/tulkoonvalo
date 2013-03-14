(function() {
  var root = this;
  var Slider = {};

  // export the name Slider, either using CommonJS modules or just assigning
  // it to the root object directly; copied from underscore.js
  if (typeof exports !== 'undefined') {
		if (typeof module !== 'undefined' && module.exports) {
	  	exports = module.exports = Slider;
		}
		exports.Slider = Slider;
  }
  else
		root['Slider'] = Slider;
    
  Slider.Slider = Backbone.Model.extend({
		// Backbone uses this to figure out where to .fetch() and .save()
		urlRoot: "../server2/sliders/",
	
	url: function() {
	    var origUrl = Backbone.Model.prototype.url.call(this);
	    return origUrl + (origUrl.charAt(origUrl.length - 1) == '/' ? '' : '/');
	},
	
	defaults: function() {
	  return {
	  	lightID: 0,
			value: 0,
			timer: 7200,
			timerDefault: 7200,
			timerMax: 86400, // 24h
			enabled: 0,
			timerEnabled: 0,
			name: "",
			children: null, // Children right under
			allChildren: null, // Children on all levels
			showChildren: false,
			childrenFetched: false,
			childElement: null,
			collection: null,
			isMaster: false,	
	  };
	
	},
	
	startTimer: function() {
			var _this = this;
			_this.interval = setInterval(function() {_this.set("timer", _this.get("timer")-1)}, 1000);
			//console.log("timer started",_this.get("timer"), this.get("name"));
			this.set("enabled", 1);
			this.set("timerEnabled", 1);
	},
	
	stopTimer: function() {
			clearInterval(this.interval);
			//console.log("timer stopped",this.get("name"));
			this.set("enabled", 0);
			this.set("timerEnabled", 0);
	},

  });

    Slider.SliderView = Backbone.View.extend({
	// Backbone constructs the view element (.el) with this tag and this class
	tagName: "div",
	className: "slider",
	
	template: _.template("\
	<div class='widget-header' />\
	<table border=0px>\
	<tr>\
		<td><div class='slider-widget' /></td>\
		<td><input class='show-children' type='image' src='../childrenarrow.png' /></td>\
	</tr>\
	<tr>\
		<td>\<input class='timer-add' type='button' value='+' /></td>\
	</tr>\
	<tr>\
		<td><input class='timer' type='text' readonly='readonly' /></td>\
		<td><input class='onoff' type='image' disabled='disabled' src='../powericon.png' /></td>\
	</tr>\
	<tr>\
		<td><input class='timer-sub' type='button' value='-' /></td>\
	</tr>\
	</table>"),
	/*
	template: _.template("<div class='widget-header' /><div class='slider-widget' />\
	<input class='timer-add' type='button' value='+' /><br />\
	<input class='timer' type='text' readonly='readonly' />\
	<input class='show-children' type='button' value='=>' /><br />\
	<input class='timer-sub' type='button' value='-' /><br />\
	<input class='onoff' type='button' value='Off' disabled='disabled' />"),
	*/

	// Backbone assigns these events automatically when the view is created
	events: {
	    "slidechange .slider-widget" : "sliderChange",
	    "click .timer-add" : function () { this.timerChange(900); },
	    "click .timer-sub" : function () { this.timerChange(-900); },
	    "click .onoff" : function () { this.enabledChange(); },
	    "click .show-children": function () { this.toggleChildren(); },
	},
	
	// Backbone calls this automatically when creating the view
	initialize: function() {
	    //this.model.bind("change", this.updateUIFromModel, this);
	    this.model.bind("remove", this.remove, this);
	    this.render();
	    var _this = this;
	    this.updateUIFromModel();
	    //this.longPoll();
	    
			this.model.bind("change:value", this.updateSliderFromModel, this);
			this.model.bind("change:timer", function() { this.timerFormat(this.timerEndCheck(-1)); }, this );
			this.model.bind("change:enabled", function() { this.updateUIFromModel(); }, this );
		},
	
	toggleChildren: function() {

		// Fetch children if not done so yet
		if (this.model.get("childrenFetched") == false) {
			this.model.set("childrenFetched", true);
			this.model.set("showChildren", true);
			this.model.get("collection").newSlider(this.model.get("children"), this);
		}
		
		else {
			// Do the actual show/hide
			var elIndex = this.$el.index()+1;
			if (this.model.get("showChildren") == true) {
				this.model.set("showChildren", false);
				this.model.get("childElement").hide("fade", 300);
			}
			else {
				this.model.set("showChildren", true);
				this.model.get("childElement").show("fade", 300);
			}
		}
	},

	// (todo: also move over name changes to the UI)
	// Disable/enable UI elements and timer
	updateUIFromModel: function() {
		if (! this.model.get("enabled")) {
			//if (this.model.get("timerEnabled"))
			this.model.stopTimer();
			isDisabled = true;
			sliderColor = "red";
			this.model.set("timer", this.model.get("timerDefault"));
			sliderColor = "red";
		}
		else {
			//if (! this.model.get("timerEnabled"))
			this.model.startTimer();
			isDisabled = false;
			sliderColor = "green";
			var timerValue = this.model.get("timerDefault");
			sliderColor = "green";
		}
		this.$(".timer").attr("disabled", isDisabled);
		this.$(".timer-add").attr("disabled", isDisabled);
		this.$(".timer-sub").attr("disabled", isDisabled);
		this.$(".onoff").attr("disabled", isDisabled);
		this.$(".timer").css({"border-color": sliderColor});
		
		// Format time for display
		this.timerFormat(this.timerEndCheck(0));
	},
	
	updateSliderFromModel: function() {
		// Disable events to prevent sliderChange calls in children
		this.undelegateEvents()
		//console.log(this.model.get("name"), this.model.get("value"));
		this.$(".slider-widget").slider("value", this.model.get("value"));
		this.delegateEvents()
	},
	
	// Change timer from buttons
	timerChange: function(timeAdd) {
		var newTime = this.timerEndCheck(timeAdd);
		// TODO: Round the added time to the nearest 15min?
		this.model.set("timer", newTime);
		  
		// Inform children
		this.childrenChange();
	},
	
	// Check if given time can be subtracted from timer
	timerEndCheck: function(timeValue) {
			var newTime = this.model.get("timer") + timeValue;
			//console.log("timer...", this.model.get("name"));	
			// Lower time limit
	    if (newTime <= 0) {
		    	this.model.stopTimer();
	  	  	return 0;
	  	}
	  	// Upper time limit
	    else if (newTime > this.model.get("timerMax"))
	    	return this.model.get("timerMax");
	    	
	  	return newTime;
	},
	
	// Format the time on UI
	timerFormat: function(timerValue) {
		var hours = Math.floor(timerValue/3600);
		var minutes = Math.floor((timerValue % 3600) / 60);

		// TODO: Always show seconds?
		// TODO: Additional proposal for UI (add red color, center font, message when time's up etc.)
		// Show second countdown when <1 min time
		if (timerValue < 60) {
			if (timerValue < 10)
				timerValue = "0"+timerValue.toString();
			this.$(".timer").val(timerValue);
		}
		
		else {	
			// Add leading '0'
			if (hours < 10)
				hours = "0"+hours.toString();
			if (minutes < 10)
				minutes = "0"+minutes.toString();
				
			this.$(".timer").val(hours+":"+minutes);
		}
	},
	
	enabledChange: function() {
		console.log("enabledChange", this.model.get("name"));
		this.model.set("enabled", false);
		
		$.get("../server2/togglesliders/"+this.model.get("lightID")+','+this.model.get("allChildren"));

		this.childrenChange();
	},
	
	sliderChange: function(ev, ui) {
		// If timer not enabled yet do it now
		//if (this.model.get("enabled") == 0)
		//		this.model.startTimer();
		this.model.set("enabled", 1);
	  this.model.set("value", this.$(".slider-widget").slider("option", "value"));
	  // if originalEvent is undefined, the event was created programmatically
	  // thus, this ensures that we don't loop
	  //if (ev.originalEvent !== undefined)
		//	var response = this.model.save();
		this.childrenChange();
	},
	
	childrenChange: function() {
		// TODO: After time-out return light values to rule levels (if none, zero)
		// TODO: Is it good thing to adjust sliders on the same hierarchy "level"?
		// TODO: Maybe timer should do timerEnd-timeNow than it's now?
		//       -> Sometimes ask server for current time
		// TODO: Long polling with remotely changed slider values?
		var coll = this.model.get("collection");
		for (var j in this.model.get("allChildren")) {
			var cid = this.model.get("allChildren")[j];
			for (var i in coll.sliderList[cid]) {
				coll.sliderList[cid][i].set("enabled", this.model.get("enabled"));
				coll.sliderList[cid][i].set("value", this.model.get("value"));
				coll.sliderList[cid][i].set("timer", this.model.get("timer"));
				console.log( coll.sliderList[cid][i].get("enabled"), "enabled" );
			}
		}
		// Insert slider values into DB
		console.log("creation", this.model.get("timer"));
		$.get("../server2/savesliders/"+this.model.get("lightID")+','+this.model.get("allChildren")+"/"+this.model.get("value")+"/"+this.model.get("timer"));
		return false;
	},
	
	// re-render the widget
	// (note: since this is a very simple view and has no subviews, it's okay to just rerender everything)
	// see http://ianstormtaylor.com/rendering-views-in-backbonejs-isnt-always-simple/ for some 
	// discussion on possible problems with larger views
	render: function () {
	    this.$el.html (this.template ({name: this.model.get('name')}));
	    this.$(".slider-widget").slider({orientation: "vertical", value: this.model.get('value')});
			
			// TODO: Is this necessary?
			// Cut too long names
			var header = this.model.get("name");
			if (header.length > 15)
				header = header.substring(0, 12)+"...";
			this.$(".widget-header").html(header);
			
			if (this.model.get("children").length == 0)
				this.$(".show-children").hide();
				
	    return this;
	},
	
    });
}).call(this);
