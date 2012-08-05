(function() {
    var root = this;
    var GroupList = {};

    if (typeof exports !== 'undefined') {
	if (typeof module !== 'undefined' && module.exports) {
	    exports = module.exports = GroupList;
	}
	exports.GroupList = GroupList;
    } else {
	root['GroupList'] = GroupList;
    }

    GroupList.GroupListNode = Backbone.Model.extend ({

    });

    GroupList.GroupList = Backbone.Collection.extend ({

    });

    GroupList.GroupListView = Backbone.View.extend ({
	


    });

}).call(this);
