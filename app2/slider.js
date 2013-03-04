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
    } else {
	root['Slider'] = Slider;
    }
    
    Slider.Slider = Backbone.Model.extend({
	// Backbone uses this to figure out where to .fetch() and .save()
	urlRoot: "../server2/sliders/",
	
	url: function() {
	    var origUrl = Backbone.Model.prototype.url.call(this);
	    return origUrl + (origUrl.charAt(origUrl.length - 1) == '/' ? '' : '/');
	},
	id: 0, // TODO: Handle me later
	
	defaults: function() {
	  return {
			value: 0,
			timer: 7200,
			timerDefault: 7200,
			timerMax: 86400, // 24h
			enabled: 0,
			header: "",
			children: [],
			showChildren: false,
			childrenFetched: false,
			childElement: null,
			collection: null,
	  };
	
	},
	
	startTimer: function() {
			var _this = this;
			console.log("timer started",_this.get("timer"));
			this.set("enabled", 1);
			
			if (_this.get("timer") > 0)
				interval = setInterval(function() {_this.set("timer", _this.get("timer")-1)}, 1000);
			else
				this.stopTimer();
	},
	
	stopTimer: function() {
			console.log("timer stopped");
			this.set("enabled", 0);
			clearInterval(interval);
	},

    });

    Slider.SliderView = Backbone.View.extend({
	// Backbone constructs the view element (.el) with this tag and this class
	tagName: "div",
	className: "slider",
	
	/*var arrowCode: function() {
		if (this.model.get("children").length > 0)
			arrowCode = "<input class='timer-sub' type='button' value='-' />";
		else arrowCode = "";
	},*/

	
	template: _.template("<div class='widget-header' /><div class='slider-widget' />\
	<input class='timer-add' type='button' value='+' /><br />\
	<input class='timer' type='text' readonly='readonly' />\
	<input class='show-children' type='button' value='=>' /><br />\
	<input class='timer-sub' type='button' value='-' /><br />\
	<input class='onoff' type='button' value='Off' />"),

	// Backbone assigns these events automatically when the view is created
	events: {
	    "slidechange .slider-widget" : "sliderChange",
	    "click .timer-add" : function () {this.timerChange(900)},
	    "click .timer-sub" : function () {this.timerChange(-900)},
	    "click .onoff" : function () {this.model.stopTimer()},
	    "click .show-children": function () { this.toggleChildren() },
	},
	
	// Backbone calls this automatically when creating the view
	initialize: function() {
	    this.model.bind("change", this.updateUIFromModel, this);
	    this.model.bind("remove", this.remove, this);
	    this.render();
	    var _this = this;
	    this.updateUIFromModel();
		},
	
	toggleChildren: function() {

		// Fetch children if not done so yet
		if (this.model.get("childrenFetched") == false) {
			this.model.set("childrenFetched", true);
			this.model.get("collection").newSlider(this.model.get("children"), this);
		}
		
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
	},

	// self-explanatory;
	// (todo: also move over name changes to the UI)
	updateUIFromModel: function() {
	
			// Disable/enable UI elements and set timer value
			if (this.model.get("enabled") == 0) {
				disabled = true;
				this.model.set("timer", this.model.get("timerDefault"));
				var timerValue = this.model.get("timerDefault");
			}
			else {
				disabled = false;
				var timerValue = this.model.get("timerDefault");
			}
			this.$(".timer").attr("disabled", disabled);
			this.$(".timer-add").attr("disabled", disabled);
			this.$(".timer-sub").attr("disabled", disabled);
			
			//console.log(this.model.get("timer"));
			this.timerFormat(this.timerEndCheck(-1));
	},
	
	updateSliderFromModel: function() {
		this.$(".slider-widget").slider("value", this.model.get("value"));
	},
	
	// Change timer from buttons
	timerChange: function(timeAdd) {
			var newTime = this.timerEndCheck(timeAdd);
			// TODO: Round the added time to the nearest 15min?
		  this.model.set("timer", newTime);
	},
	
	// Check if given time can be subtracted from timer
	timerEndCheck: function(timeValue) {
			var newTime = this.model.get("timer") + timeValue;
			
			// Lower time limit
	    if (newTime < 0) {
		    	this.model.stopTimer();
	  	  	return 0;
	  	}
	  	// Upper time limit
	    else if (newTime > this.model.get("timerMax"))
	    	return this.model.get("timerMax");
	    	
	  	return newTime;
	},
	
	timerParse: function() {
	},
	
	// Format the time on UI
	timerFormat: function(timerValue) {
			var hours = Math.floor(timerValue/3600);
			var minutes = Math.floor((timerValue % 3600) / 60);

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
	
	sliderChange: function(ev, ui) {
			// If timer not enabled yet do it now
			if (this.model.get("enabled") == 0)
					this.model.startTimer();
			
	    this.model.set("value", this.$(".slider-widget").slider("option", "value"));
	    // if originalEvent is undefined, the event was created programmatically
	    // thus, this ensures that we don't loop
	    if (ev.originalEvent !== undefined)
				this.model.save();

	    return false;
	},
	
	// re-render the widget
	// (note: since this is a very simple view and has no subviews, it's okay to just rerender everything)
	// see http://ianstormtaylor.com/rendering-views-in-backbonejs-isnt-always-simple/ for some 
	// discussion on possible problems with larger views
	render: function () {
	    this.$el.html (this.template ({name: this.model.get('name')}));
	    this.$(".slider-widget").slider({orientation: "vertical", value: this.model.get('value')});
			this.$(".widget-header").html(this.model.get("header")); // TODO: This OK here?
	    return this;
	},
	
    });
}).call(this);
