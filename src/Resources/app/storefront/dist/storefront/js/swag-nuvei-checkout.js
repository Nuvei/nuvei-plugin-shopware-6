(window.webpackJsonp = window.webpackJsonp || []).push([
    ["swag-nuvei-checkout"], {
        zqh6: function(e, t, n) {
            "use strict";
            n.r(t);
            var o = n("k8s9");

            function r(e) {
                return (r = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function(e) {
                    return typeof e
                } : function(e) {
                    return e && "function" == typeof Symbol && e.constructor === Symbol && e !== Symbol.prototype ? "symbol" : typeof e
                })(e)
            }

            function c(e, t) {
                if (!(e instanceof t)) throw new TypeError("Cannot call a class as a function")
            }

            function i(e, t) {
                for (var n = 0; n < t.length; n++) {
                    var o = t[n];
                    o.enumerable = o.enumerable || !1, o.configurable = !0, "value" in o && (o.writable = !0), Object.defineProperty(e, o.key, o)
                }
            }

            function u(e, t) {
                return !t || "object" !== r(t) && "function" != typeof t ? function(e) {
                    if (void 0 === e) throw new ReferenceError("this hasn't been initialised - super() hasn't been called");
                    return e
                }(e) : t
            }

            function a(e) {
                return (a = Object.setPrototypeOf ? Object.getPrototypeOf : function(e) {
                    return e.__proto__ || Object.getPrototypeOf(e)
                })(e)
            }

            function f(e, t) {
                return (f = Object.setPrototypeOf || function(e, t) {
                    return e.__proto__ = t, e
                })(e, t)
            }
            var s = function(e) {
                function t() {
                    return c(this, t), u(this, a(t).apply(this, arguments))
                }
                var n, r, s;
                return function(e, t) {
                    if ("function" != typeof t && null !== t) throw new TypeError("Super expression must either be null or a function");
                    e.prototype = Object.create(t && t.prototype, {
                        constructor: {
                            value: e,
                            writable: !0,
                            configurable: !0
                        }
                    }), t && f(e, t)
                }(t, e), n = t, (r = [{
                    key: "init",
                    value: function() {
                        this._client = new o.a, window.addEventListener("load", this.onLoad.bind(this))
                    }
                }, {
                    key: "onLoad",
                    value: function() {
                        console.log(".checkout-main loaded 2");
                        
                        var e = document.createElement("script");
                        e.onload = function() {
                            console.log("checkout loaded")
                        }, 
                        e.src = "https://cdn.safecharge.com/safecharge_resources/v1/checkout/checkout.js", 
                        document.head.appendChild(e)
                
                        
                    }
                }]) && i(n.prototype, r), s && i(n, s), t
            }(n("FGIj").a);
            window.PluginManager.register("NuveiStorefront", s, '[class="checkout-main"]')
        }
    },
    [
        ["zqh6", "runtime", "vendor-node", "vendor-shared"]
    ]
]);