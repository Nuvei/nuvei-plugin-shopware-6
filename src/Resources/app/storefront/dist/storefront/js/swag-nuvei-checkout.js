"use strict";(self.webpackChunk=self.webpackChunk||[]).push([["swag-nuvei-checkout"],{9602:(e,n,t)=>{var o=t(8254),c=t(6285);class d extends c.Z{init(){console.log("NuveiCheckout imit()"),this._client=new o.Z,window.addEventListener("load",this.onLoad.bind(this))}onLoad(){if(console.log(".checkout-main loaded"),0==document.querySelector('input[name="paymentMethodId"]').length)return;let e=document.querySelector(".checkout-main").closest(".container"),n=document.createElement("div");n.id="nuvei_checkout",e.appendChild(n);let t=document.querySelector("#confirmFormSubmit").closest("form"),o=document.createElement("input"),c=document.createElement("input");o.type="hidden",o.name="nuveiPaymentMethod",o.value="",c.type="hidden",c.name="nuveiTransactionId",c.value="",t.appendChild(o),t.appendChild(c);var d=document.createElement("script");d.onload=function(){console.log("nuvei temp file loaded");var e=document.createElement("script");e.onload=function(){console.log("nuvei checkout loaded"),nuveiRenderCheckout()},e.src="https://cdn.safecharge.com/safecharge_resources/v1/checkout/checkout.js",document.head.appendChild(e)},d.src="/bundles/swagnuveicheckout/storefront/js/nuvei.js",document.head.appendChild(d)}}console.log("nuveu store main js");window.PluginManager.register("NuveiCheckout",d,'[class="checkout-main"]')}},e=>{e.O(0,["vendor-node","vendor-shared"],(()=>{return n=9602,e(e.s=n);var n}));e.O()}]);
