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
			timerLast: null,
			timerDefault: 7200,
			timerMax: 86400, // 24h
			timerEnabled: false,
			enabled: false,
			alreadyEnabled: false,
			name: "",
			children: null, // Children right under
			allChildren: null, // Children on all levels
			showChildren: false,
			childrenFetched: false,
			childElement: null,
			level: 1,
			//ghost: 0,
	  };
	
	},
	
	startTimer: function() {
			if (! this.get("timerEnabled")) {
				var _this = this;
				_this.interval = setInterval(function() {_this.set("timer", _this.get("timer")-100)}, 1000);
				this.set("timerEnabled", true);
			}
	},
	
	stopTimer: function() {
			clearInterval(this.interval);
			//console.log("timer stopped",this.get("name"));
			this.set("timerEnabled", false);
			this.set("enabled", false);
	},

  });

    Slider.SliderView = Backbone.View.extend({
	// Backbone constructs the view element (.el) with this tag and this class
	tagName: "div",
	className: "slider",
	
	template: _.template("\
	<div class='widget-header' id='0'>\
	</div>\
	<table border=0px>\
	<tr>\
		<td><div class='slider-widget'></td>\
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

	// Backbone assigns these events automatically when the view is created
	events: {
	    "slidechange .slider-widget" : "sliderChangeUser",
	    "click .timer-add" : function () { this.timerChange(900); },
	    "click .timer-sub" : function () { this.timerChange(-900); },
	    "click .onoff" : function () { this.enabledChange(false, false); },
	    "click .show-children": function () { this.toggleChildren(); },
	},
	
	// Backbone calls this automatically when creating the view
	initialize: function() {
	    //this.model.bind("change", this.updateUIFromModel, this);
	    this.model.bind("remove", this.remove, this);
	    this.render();
	    var _this = this;
	   // this.updateUIFromModel();
	   //this.enabledChange(this.model.get("enabled"));
	   this.enabledChange(this.model.get("enabled"), false);
	    
			this.model.bind("change:enabled", function(e) { this.enabledChange(this.model.get("enabled")); }, this );
			this.model.bind("change:value", function() { this.updateSliderWidget(); }, this);
			this.model.bind("change:timer", function() { this.timerFormat(this.timerEndCheck(-1)); }, this );
	},
	
	toggleChildren: function() {
	
		// Fetch children if not done so yet
		if (this.model.get("childrenFetched") == false) {
			this.model.set("childrenFetched", true);
			this.model.set("showChildren", true);
			this.model.collection.newSlider(this.model.get("children"), this);
			this.$(".show-children").attr("src", "../childrenarrow_back2.png");
		}
		
		else {
			// Do the actual show/hide
			var elIndex = this.$el.index()+1;
			if (this.model.get("showChildren") == true) {
				this.model.set("showChildren", false);
				this.model.get("childElement").hide("fade", 300);
				this.$(".show-children").attr("src", "../childrenarrow2.png");
			}
			else {
				this.model.set("showChildren", true);
				this.model.get("childElement").show("fade", 300);
				this.$(".show-children").attr("src", "../childrenarrow_back2.png");
			}
		}
	},
	
	updateSliderWidget: function() {
		// Disable events to prevent cascading calls in children
		this.undelegateEvents();
		this.$(".slider-widget").slider("value", this.model.get("value"));
		this.delegateEvents();
		
		// Numeral next to the slider
		this.$(".slider-widget #ui-slider-handle-value").html(this.model.get("value"));
	},
	
	sliderChangeUser: function(ev, ui) {
	  this.model.set("value", this.$(".slider-widget").slider("option", "value"));
	  
	  // Call enabledChange is not enabled
		if (! this.model.get("enabled")) {
			this.model.set("enabled", true);
		}
		else this.childrenChange();
	},
	
	// Change timer from buttons
	timerChange: function(timeAdd) {
		var newTime = this.timerEndCheck(timeAdd);
		// TODO: Round the added time to the nearest 15min?
		this.model.set("timer", newTime);
		//this.model.set("timerLast", newTime);  
		this.childrenChange();
	},
	
	enabledChange: function(enabled=false, save=true) {
		this.model.set("enabled", enabled);
		
		if (! enabled) {
			this.model.set("alreadyEnabled", false);
			this.model.set("timer", 0);
			this.model.set("value", 0);
			this.model.stopTimer();
			timerColor = "red";
		}
		else {
			// Don't set time to default if already enabled
			if (! this.model.get("alreadyEnabled")) {
				this.model.set("alreadyEnabled", true);
				this.model.set("timer", this.model.get("timerDefault"));
			}
			this.model.startTimer();
			timerColor = "green";
		}
		
		this.$(".timer").attr("disabled", ! enabled);
		this.$(".timer-add").attr("disabled", ! enabled);
		this.$(".timer-sub").attr("disabled", ! enabled);
		this.$(".onoff").attr("disabled", ! enabled);
		this.$(".timer").css({"border-color": timerColor});
		
		// Format time for display
		this.timerFormat(this.timerEndCheck(0));
		
		// Don't save when caller is init or onOff button is pressed
		if (save) this.childrenChange();

	},
	
	childrenChange: function() {
		// Set children values
		var coll = this.model.collection;
		for (var j in this.model.get("allChildren")) {
			var cid = this.model.get("allChildren")[j];
			for (var i in coll.sliderList[cid]) {
				coll.sliderList[cid][i].set("enabled", this.model.get("enabled"));
				coll.sliderList[cid][i].set("value", this.model.get("value"));
				coll.sliderList[cid][i].set("timer", this.model.get("timer"));
				coll.sliderList[cid][i].set("timerLast", this.model.get("timer"));
			}
		}
		
		// Insert slider values into DB
		$.post(
			"../server2/savesliders",
			JSON.stringify(
			{"ids": this.model.get("lightID")+','+this.model.get("allChildren"),
			"value": this.model.get("value"),
			"timer": this.model.get("timer")})
		);

		return false;
	},

	// Check if given time can be subtracted from timer
	timerEndCheck: function(timeValue) {
		var newTime = this.model.get("timer") + timeValue;
		// Lower and upper time limits
		if (newTime <= 0) {
				this.model.stopTimer();
				return 0;
		}
		else if (newTime > this.model.get("timerMax"))
			return this.model.get("timerMax");
			
		return newTime;
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
	
	
	// re-render the widget
	// (note: since this is a very simple view and has no subviews, it's okay to just rerender everything)
	// see http://ianstormtaylor.com/rendering-views-in-backbonejs-isnt-always-simple/ for some 
	// discussion on possible problems with larger views
	render: function () {
	    this.$el.html (this.template ({name: this.model.get('name')}));
	    this.$(".slider-widget").slider({orientation: "vertical", value: this.model.get('value')});
			
			// TODO: See license on jquery.ui.touch-punch.min.js library
			this.$(".slider-widget").draggable();
			this.$(".slider-widget .ui-slider-handle").append("<div id='ui-slider-handle-value'></div>");
			this.$(".slider-widget #ui-slider-handle-value").html(this.model.get("value"));
			
			// TODO: Is this necessary?
			// Cut too long names
			var header = this.model.get("name");
			if (header.length > 15)
				header = header.substring(0, 12)+"...";
			this.$(".widget-header").html(header);
			this.$(".widget-header").html(header+"<input class='show-children' type='image' src='../childrenarrow2.png' />");
			if (this.model.get("children").length == 0)
				this.$(".show-children").hide();
				
	    return this;
	},
	
    });
}).call(this);
