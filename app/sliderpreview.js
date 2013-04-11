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
		
		"click .codePreview": function() {
			var popup = window.open('','name','height=400,width=500');
			popup.document.write(this.model.get("slidersCodeStart")+this.model.get("sliderIDs")+
				this.model.get("slidersCodeEnd"));
			popup.document.close();
		}
	},

	initialize: function() {
		this.render();
		var _this = this;
		
        this.model.bind("remove", function() { this.remove(); }, this);
        
		// Get the code for sliders
		$.get('../app2/index-text.html', 
		function(response) {
			// Get the two halves from the code
			div = _this.model.get("slidersCodeDiv");
			startIndex = response.search(div);
			_this.model.set("slidersCodeStart", response.substr(0, startIndex));
			_this.model.set("slidersCodeEnd", response.substr(startIndex+div.length));
		});
		
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
    var treeChildren = $(this.$("#sliderSelected")[0].children[0].childNodes);
		for (var x=0; x<treeChildren.size(); x++)
			sliderIDs.push(treeChildren[x].id);
		this.model.set("sliderIDs", sliderIDs);
		this.newSliders();
	},

	newSliders: function() {
		var newIDs = this.model.get("sliderIDs");
		
		// Add new sliders and the HTML code
		if (newIDs.length > 0) {
			this.$("#slidersCode").val(this.model.get("slidersCodeStart")+newIDs+
				this.model.get("slidersCodeEnd"));
		}
		else
			this.$("#slidersCode").val("");
	},
	
	template: _.template("<table>\
	<tr>\
		<td id='groupTableCell'>\
			<div id='groupTableContainer' class='programs'>\
				<b>Ryhmät</b><br /><div id='sliderLightGroups'></div>\
			</div>\
		</td>\
		<td id='groupTableCell'>\
			<div id='groupTableContainer' class='programs jstree-drop'>\
				<b>Kytkimen ryhmät</b>\
				<div id='sliderSelected'></div>\
			</div>\
		</td>\
	</tr>\
	<tr><td></td><td><input id='deleteSliderGroup' type='submit' value='Poista valittu' /></td></tr>\
	</table>\
	<br />\
	<div class='codePreviewContainer'>\
		Kopioitava HTML-koodi:<a class='codePreview' href=#>Näytä kytkimien esikatselu</a>\
		<textarea id='slidersCode' type='textarea' readonly='readonly' rows='15' cols='40'></textarea>\
	</div>"),
	
	render: function() {
	    this.$el.html (this.template());
	    return this;
	},
	
  })
    
}).call(this);
