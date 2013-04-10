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
		// TODO: After removing inserting doesn't work
		// TODO: If node is selected nodes are inserted inside it which shouldn't happen
		"click #deleteSliderGroup": function (event) {
			$.jstree._reference(this.$("#sliderSelected")).remove();
			this.setSliderIDs();
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
		this.tree = this.$("#sliderLightGroups").jstree ({
			"json_data": {
					"ajax": {
							"url": "../server2/groupsTree/1",
					}
			},
			"crrm": { move : { "always_copy": "multitree" } },
			"dnd" : {
				"drop_finish" : function (data) {
					$("#sliderSelected").jstree("create", -1, "last",
					{"data":data.o[0].children[1].text, "attrs":{"id":data.o[0].id}}, false, true);
				},
			},
			"types" : treeSettings[0],
			"plugins": treeSettings[1],
		})
		
		.bind("loaded.jstree", function (e, data) {
			data.inst.open_node("#-1");
		})
		
		this.tree = this.$("#sliderSelected").jstree ({
			"json_data": {
					"data": {},
					//"data": {"data": "Vedät ryhmät tänne", "attr": {"id":1, "rel":"root"} },
			},
			"types" : { "max_depth": 1 },
			"plugins": treeSettings[1],
		})
		
		.bind("create.jstree", function (e, data) {
    	data.rslt.obj[0].id = data.args[2]["attrs"].id;
    	_this.setSliderIDs();
    })
	},
	
	// Create array from the tree node IDs
	setSliderIDs: function(data) {
		sliderIDs = [];
		console.log($(this.$("#sliderSelected")));
    var treeChildren = $(this.$("#sliderSelected")[0].children[0].childNodes);
		for (var x=0; x<treeChildren.size(); x++) {
			console.log(treeChildren[x].id);
			sliderIDs.push(treeChildren[x].id);
		}
		this.model.set("sliderIDs", sliderIDs);
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
		coll.newSlider(newIDs, null);
        
        if (newIDs.length==0)
        	this.$("#slidersCode").val("");
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
			<input id='deleteSliderGroup' type='submit' value='Poista valittu' />\
		</td>\
	</tr>\
	</table>\
	<div>\
		Kopioitava HTML-koodi:\
		<textarea id='slidersCode' type='textarea' readonly='readonly' rows='15' cols='40'></textarea>\
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
