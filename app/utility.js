(function() {

    var root = this;
    var Utility = {};

    if (typeof exports !== 'undefined') {
	if (typeof module !== 'undefined' && module.exports) {
	    exports = module.exports = Utility;
	}
	exports.Utility = Utility;
    } else {
	root['Utility'] = Utility;
    }

}).call(this);
