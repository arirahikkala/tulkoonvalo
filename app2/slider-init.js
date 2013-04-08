
var SliderCollection = Backbone.Collection.extend ({
	model: Slider.Slider,
	lightIds : [],
	parentEl: null,
	
	sliderList: {},
	sliderIDs: [],

	url: function() {
		return "../server2/data/"+this.lightIds
	},

	initialize: function() {
		this.longPoll();
	},

	newSlider: function(lightList, parentEl) {
		this.lightIds = lightList;
		
		// No parents in first creation
		if (parentEl)
			this.parentEl = parentEl;
			
		this.fetch();
	},

	parse: function(response) {
		// Reset long polling
		this.pollEnabled = false;
		this.allowPolling = false;
		if (this.query) this.query.abort();
		
		var childWrap = null;
		//var now = new Date();

		for (var i in response) {
			var curLight = response[i];
			var isEnabled = false;
			var isMaster = false;
			
			// A slider may have timer enabled
			if (curLight["timer"] > 0)
				isEnabled = true;
			
			// Create a new slider with some data, rest are long-polled
			this.add ({ name: curLight["name"], 
			children: curLight["children"], allChildren: curLight["all_children"], 
			lightID: curLight["permanent_id"], 
			enabled: isEnabled, timer: curLight["timer"], timerLast: curLight["timer_full"], level: null
			//value: curLight["current_level"], ghost: curLight["ghost"]
			});
			
			// Keep record of sliders with a certain ID
			var cid = curLight["permanent_id"];
			if (! this.sliderList[cid]) {
				this.sliderList[cid] = [];
				this.sliderIDs.push(cid);
			}
			this.sliderList[cid].push(this.models[this.length-1])
			
			// Create an element for children and insert them in it
			if (this.parentEl) {
				if (! childWrap) {
					childWrap = $("<div class='bar' />");
					this.parentEl.$el.after(childWrap);
					this.parentEl.model.set("childElement", childWrap);
				}

				var childEl = $(".slider:last");
				childWrap.append(childEl);
				
				if (this.parentEl.model.get("level")%2 == 0)
					levelColor = "red";
			else
				levelColor = "yellow";

			this.models[this.length-1].set("level", this.parentEl.model.get("level")+1);
			headEl = $(childEl.children()[0]);
			headEl.attr("id", levelColor);
			console.log(this.models[this.length-1].get("level"));
			childEl.css({"margin-top":this.models[this.length-1].get("level")*0.5+"em"});
			}
		}
		if (childWrap) {
			childWrap.hide();
			childWrap.show("fade", 150);
		}
		this.allowPolling = true;
		return this.models;
	},

	// Poll only if not already polling 
	longPoll: function() {
		_this = this;
		this.poll = setInterval(function() {
			if (! _this.pollEnabled && _this.allowPolling) {
				_this.pollEnabled = true;
				_this.longPollGet()
			}
		}, 100);
	},
	
	// Get slider value (if changed from somewhere else) and rule value for ghost slider
	longPollGet: function() {
		_this = this;
		
		// TODO: This is so ugly
		var sliderValues = [];
		var timerValues = [];
		var enabledValues = [];
		
		// Create arrays to be sent
		for (var cid in this.sliderList) {
			sliderValues.push(this.sliderList[cid][0].get("value"));
			timerValues.push(this.sliderList[cid][0].get("timerLast"));
			enabledValues.push(this.sliderList[cid][0].get("enabled"));
		}
		
		this.query = $.get("../server2/poll/"+this.sliderIDs+"/"+sliderValues+"/"+timerValues+"/"+enabledValues,
			function(response) {
				_this.pollEnabled = false;
				if (response) {
					for (var cid in response) {
						for (var i in _this.sliderList[cid]) {
							_this.sliderList[cid][i].set("enabled", response[cid]["enabled"]);
							_this.sliderList[cid][i].set("value", response[cid]["current_level"]);
							_this.sliderList[cid][i].set("timer", response[cid]["timer"]);
							_this.sliderList[cid][i].set("timerLast", response[cid]["timer"]);
						}
					}
				}
			},
		"json");
	},
	
});

var SliderCollectionView = Backbone.View.extend ({
	tagName: "div",
	className: "slider-collection",

	initialize: function() {
		var _this = this;
		this.model.on ("add", function (m, c) { _this.$el.append (new Slider.SliderView ({model: m}).$el)});
		this.render();
	},

	render: function() {
		this.$el.empty();
		var _this = this;
		this.model.each (function (x) { _this.$el.append (new Slider.SliderView ({model: x}).$el)});
	}
});