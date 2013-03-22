(function() {
    var root = this;
    var SliderPreview = {};

    if (typeof exports !== 'undefined') {
	if (typeof module !== 'undefined' && module.exports) {
	    exports = module.exports = SliderPreview;
	}
	exports.SliderPreview = SliderPreview;
    } else {
	root['SliderPreview'] = SliderPreview;
    }

    SliderPreview.SliderPreview = Backbone.RelationalModel.extend ({

	defaults: function() {
	  return {
	  	id: null,
	  	sliderIDs: [],
	  	slidersCodeStart: null,
	  	slidersCodeEnd: null,
	  	slidersCodeDiv: "<!--SLIDER_ARRAY-->",
	  	SliderCollection: null,
	  	SlidersView: null,
	  }
	},

	});
	
  SliderPreview.SliderPreviewView = Backbone.View.extend ({
	tagName: "div",
	className: "sliderpreview-item",

	events: {
		"click #level-item-remove" : function() {
			if (! this.model.get("new_level"))  {
				var choice = confirm("Haluatko varmasti poistaa ajan?");
				if (choice) {
					this.model.set("allow_delete", true)			
					this.remove();
				}
			}
			else this.model.destroy();
		},
		
		// Slider values changed
		"change #slidersIDs" : function() { this.setSliderIDs() },
		"slidechange #light-slider" : function () { this.model.set("light_level", this.$("#light-slider").slider("value")); },
		"slidechange #motion-slider" : function () { this.model.set("motion_level", this.$("#motion-slider").slider("value")); },
	
		// Checkboxes clicked
		"click #light-enabled" : function (event) { this.model.set("light_detector", event.target.checked); },
		"click #motion-enabled" : function (event) { this.model.set("motion_detector", event.target.checked); },
	},

	// TODO: This according to jstree
	setSliderIDs: function() {
		var newIDs = this.$("#slidersIDs").val().split(',');
		this.model.set("sliderIDs", newIDs );
		this.newSliders(newIDs);
	},

	newSliders: function(newIDs) {
		// Remove old sliders
		var coll = this.model.get("SliderCollection");
		for (var i=coll.length; i>0; i--) {
			coll.remove(coll.models[i-1]);
		}
		
		// Create new code and sliders
		console.log(newIDs);
		this.$("#slidersCode").val(this.model.get("slidersCodeStart")+"["+newIDs+"]"+this.model.get("slidersCodeEnd"));
		this.model.get("SliderCollection").newSlider(newIDs, null);
	},

	initialize: function() {
			var _this = this;
			$.get('../app2/index-text.html', 
			function(response) {
				// Get the two halves from the code
				div = _this.model.get("slidersCodeDiv");
				startIndex = response.search(div);
				_this.model.set("slidersCodeStart", response.substr(0, startIndex));
				_this.model.set("slidersCodeEnd", response.substr(startIndex+div.length));
			});
			
			this.model.bind("change:errors", function() { console.log( "asd",this.model.get("errors") );this.drawErrors() }, this );
	    this.model.bind ("remove", this.remove, this);
	    this.model.bind ("change:name", this.render, this);
	    this.render();
	    
	 		this.model.set("SliderCollection", new SliderCollection());
    	this.model.set("SlidersView", new SliderCollectionView ({ model: this.model.get("SliderCollection"), el: this.$("#sliderWidgets") }));
	},
	
	template: _.template("\
  <div>\
		<input id='slidersIDs' />\
	</div>\
	<div>\
		Kopioitava HTML-koodi:\
		<textarea id='slidersCode' type='textarea' rows='15' cols='40'></textarea>\
	</div>\
	<div id='slidersPreview'>Säätimien esikatselu:\
		<div class='bar' id='sliderWidgets'></div>\
	</div>"),
	
	render: function() {
	    this.$el.html (this.template() );
	    return this;
	},
	
  })
    
}).call(this);
