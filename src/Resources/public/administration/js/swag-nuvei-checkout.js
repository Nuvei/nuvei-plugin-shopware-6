!function(t){var n={};function e(s){if(n[s])return n[s].exports;var i=n[s]={i:s,l:!1,exports:{}};return t[s].call(i.exports,i,i.exports,e),i.l=!0,i.exports}e.m=t,e.c=n,e.d=function(t,n,s){e.o(t,n)||Object.defineProperty(t,n,{enumerable:!0,get:s})},e.r=function(t){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(t,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(t,"__esModule",{value:!0})},e.t=function(t,n){if(1&n&&(t=e(t)),8&n)return t;if(4&n&&"object"==typeof t&&t&&t.__esModule)return t;var s=Object.create(null);if(e.r(s),Object.defineProperty(s,"default",{enumerable:!0,value:t}),2&n&&"string"!=typeof t)for(var i in t)e.d(s,i,function(n){return t[n]}.bind(null,i));return s},e.n=function(t){var n=t&&t.__esModule?function(){return t.default}:function(){return t};return e.d(n,"a",n),n},e.o=function(t,n){return Object.prototype.hasOwnProperty.call(t,n)},e.p=(window.__sw__.assetPath + '/bundles/swagnuveicheckout/'),e(e.s="4Ubl")}({"4Ubl":function(t,n,e){"use strict";e.r(n);console.log("admin index.js");var s=document.createElement("script");s.src="/bundles/swagnuveicheckout/administration/js/nuvei-admin.js",document.head.appendChild(s),Shopware.Component.override("sw-order-detail-base",{template:'\n{% block sw_order_detail_base__user_card %}\n\t{% parent %}\n\t\n\t<div id="nuveiActions" >\n\t\t<div class="sw-card has--header has--title">\n\t\t\t<div class="sw-card__header">\n\t\t\t\t<div class="sw-card__titles">\n\t\t\t\t\t<div class="sw-card__title">Nuvei actions</div>\n\t\t\t\t</div>\n\n\t\t\t\t<img src="/bundles/swagnuveicheckout/storefront/img/rolling.gif" \n\t\t\t\t\t style="display: none;" \n\t\t\t\t\t id="nuveiLoader" \n\t\t\t\t\t width="20">\n\t\t\t</div>\n\n\t\t\t<div class="sw-card__content">\n\t\t\t\t<div class="sw-container" style="display: block;">\n\t\t\t\t\t<button class="sw-button sw-button--primary nuveiButton" id="nuveiRefundBtn" type="button" style="margin-right: 5px; display: none;">\n\t\t\t\t\t\t<span class="sw-button__content">Refund</span>\n\t\t\t\t\t</button>\n\n\t\t\t\t\t<button class="sw-button sw-button--primary nuveiButton" id="nuveiVoidBtn" type="button" style="margin-right: 5px; display: none;">\n\t\t\t\t\t\t<span class="sw-button__content">Void</span>\n\t\t\t\t\t</button>\n\n\t\t\t\t\t<button class="sw-button sw-button--primary nuveiButton" id="nuveiSettleBtn" type="button" style="margin-right: 5px; display: none;">\n\t\t\t\t\t\t<span class="sw-button__content">Settle</span>\n\t\t\t\t\t</button>\n\t\t\t\t</div>\n\t\t\t</div>\n\t\t</div>\n\t</div>\n\n\t<div id="nuveiNotes">\n\t\t<div class="sw-card has--header has--title">\n\t\t\t<div class="sw-card__header">\n\t\t\t\t<div class="sw-card__titles">\n\t\t\t\t\t<div class="sw-card__title">Nuvei notes</div>\n\t\t\t\t</div>\n\t\t\t</div>\n\n\t\t\t<div class="sw-card__content">\n\t\t\t\t<table style="width: 100%; display: none;" border="0">\n\t\t\t\t\t<tr>\n\t\t\t\t\t\t<th style="width: 150px; text-align: left;">Date</th>\n\t\t\t\t\t\t<th style="text-align: left;">Note</th>\n\t\t\t\t\t</tr>\n\t\t\t\t</table>\n\t\t\t</div>\n\t\t</div>\n\t</div>\n{% endblock %}',created:function(){console.log("sw-order-detail-base created"),runNuveiScripts()}}),Shopware.Component.override("sw-order-detail-general",{template:'\n{% block sw_order_detail_general_info_card %}\n\t{% parent %}\n\t\n\t{{ shopware.version }}\n\t\n\t<div id="nuveiActions" >\n\t\t<div class="sw-card has--header has--title">\n\t\t\t<div class="sw-card__header">\n\t\t\t\t<div class="sw-card__titles">\n\t\t\t\t\t<div class="sw-card__title">Nuvei actions</div>\n\t\t\t\t</div>\n\n\t\t\t\t<img src="/bundles/swagnuveicheckout/storefront/img/rolling.gif" \n\t\t\t\t\t style="display: none;" \n\t\t\t\t\t id="nuveiLoader" \n\t\t\t\t\t width="20">\n\t\t\t</div>\n\n\t\t\t<div class="sw-card__content">\n\t\t\t\t<div class="sw-container" style="display: block;">\n\t\t\t\t\t<button class="sw-button sw-button--primary nuveiButton" id="nuveiRefundBtn" type="button" style="margin-right: 5px; display: none;">\n\t\t\t\t\t\t<span class="sw-button__content">Refund</span>\n\t\t\t\t\t</button>\n\n\t\t\t\t\t<button class="sw-button sw-button--primary nuveiButton" id="nuveiVoidBtn" type="button" style="margin-right: 5px; display: none;">\n\t\t\t\t\t\t<span class="sw-button__content">Void</span>\n\t\t\t\t\t</button>\n\n\t\t\t\t\t<button class="sw-button sw-button--primary nuveiButton" id="nuveiSettleBtn" type="button" style="margin-right: 5px; display: none;">\n\t\t\t\t\t\t<span class="sw-button__content">Settle</span>\n\t\t\t\t\t</button>\n\t\t\t\t</div>\n\t\t\t</div>\n\t\t</div>\n\t</div>\n\n\t<div id="nuveiNotes">\n\t\t<div class="sw-card has--header has--title">\n\t\t\t<div class="sw-card__header">\n\t\t\t\t<div class="sw-card__titles">\n\t\t\t\t\t<div class="sw-card__title">Nuvei notes</div>\n\t\t\t\t</div>\n\t\t\t</div>\n\n\t\t\t<div class="sw-card__content">\n\t\t\t\t<table style="width: 100%; display: none;" border="0">\n\t\t\t\t\t<tr>\n\t\t\t\t\t\t<th style="width: 150px; text-align: left;">Date</th>\n\t\t\t\t\t\t<th style="text-align: left;">Note</th>\n\t\t\t\t\t</tr>\n\t\t\t\t</table>\n\t\t\t</div>\n\t\t</div>\n\t</div>\n{% endblock %}',created:function(){console.log("sw-order-detail-general created"),runNuveiScripts()}})}});
//# sourceMappingURL=swag-nuvei-checkout.js.map