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
		"click #removeSliderID": function (event) {
			// Remove the ul element and the id from the list
			var remParent = $(event.target).parent();
			this.model.get("sliderIDs").splice(remParent.index(), 1);
			remParent.remove();
			
			this.newSliders();
		},
	},

	initialize: function() {
	    this.render();
			var _this = this;
			
			// Get the code for sliders
			$.get('../app2/index-text.html', 
			function(response) {
				// Get the two halves from the code
				div = _this.model.get("slidersCodeDiv");
				startIndex = response.search(div);
				_this.model.set("slidersCodeStart", response.substr(0, startIndex));
				_this.model.set("slidersCodeEnd", response.substr(startIndex+div.length));
			});
			
	 		this.model.set("SliderCollection", new SliderCollection());
    	this.model.set("SlidersView", new SliderCollectionView ({ model: this.model.get("SliderCollection"), el: this.$("#sliderWidgets") }));
    	
		var treeSettings = this.model.collection.getTreeSettings();
		_this = this;
		this.tree = this.$("#sliderLightGroups").jstree ({
			"json_data": {
					"ajax": {
							"url": "../server2/groupsTree/1",
					}
			},
			"dnd" : {
				"drop_finish" : function (data,r) {
					console.log("DROP",data,r);
					 $("#sliderSelected").jstree("create",-1,false,"No rename",false,true); 

					//$(data.rslt.obj).attr("id", r);
					//data.inst.refresh();
				},
				"drop_check" : function(data) {console.log(data);return true;},
			},
			"types" : treeSettings[0],
			"plugins": treeSettings[1],
		})
		
		this.tree = this.$("#sliderSelected").jstree ({
			"json_data": {
					"data": {},
			},
			"types": {
				"light": { max_children: 0 }
			},
			"crrm": { move : { "always_copy": "multitree" } },
			"types" : treeSettings[0],
			"plugins": treeSettings[1],
		})
	},
	
	setSliderIDs: function(data) {
		var newID = $(data.o[0]).attr("id");
		var newName = $($(data.o[0]).find('a')[0]).text();
		
		this.model.get("sliderIDs").push(newID);
		this.$("#selectedIDs").append("<li id='"+newID+"'>"+newName+"<input id='removeSliderID' type=button value='Poista'></li>");
	
		this.newSliders();
	},

	newSliders: function() {
		var newIDs = this.model.get("sliderIDs");
	
		// Remove old sliders
		var coll = this.model.get("SliderCollection");
		for (var i=coll.length; i>0; i--)
			coll.remove(coll.models[i-1]);
		
		// Create new code and sliders
		this.$("#slidersCode").val(this.model.get("slidersCodeStart")+newIDs+this.model.get("slidersCodeEnd"));
		this.model.get("SliderCollection").newSlider(newIDs, null);
	},
	
	template: _.template("\
	<table>\
	<tr>\
		<td>\
			<div id='groupTableContainer' class='programs'>\
				<div id='sliderLightGroups'></div>\
			</div>\
		</td>\
	</tr>\
	<tr>\
		<td>\
			<div id='groupTableContainer' class='programs jstree-drop'>\
				<b>Vedä ryhmät tänne</b>\
				<div id='sliderSelected'></div>\
			</div>\
		</td>\
	</tr>\
	</table>\
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
