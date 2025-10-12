import{dI as s,dJ as l,dK as o,dL as e,dM as a,d as f,dN as p,dO as b,aw as g,dP as m,dQ as h,s as $}from"./index-C4bwFPRU.js";const t="0!important",u="-1px!important";function i(r){return e(`${r}-type`,[o("& +",[s("button",{},[e(`${r}-type`,[a("border",{borderLeftWidth:t}),a("state-border",{left:u})])])])])}function n(r){return e(`${r}-type`,[o("& +",[s("button",[e(`${r}-type`,[a("border",{borderTopWidth:t}),a("state-border",{top:u})])])])])}const y=s("button-group",`
 flex-wrap: nowrap;
 display: inline-flex;
 position: relative;
`,[l("vertical",{flexDirection:"row"},[l("rtl",[s("button",[o("&:first-child:not(:last-child)",`
 margin-right: ${t};
 border-top-right-radius: ${t};
 border-bottom-right-radius: ${t};
 `),o("&:last-child:not(:first-child)",`
 margin-left: ${t};
 border-top-left-radius: ${t};
 border-bottom-left-radius: ${t};
 `),o("&:not(:first-child):not(:last-child)",`
 margin-left: ${t};
 margin-right: ${t};
 border-radius: ${t};
 `),i("default"),e("ghost",[i("primary"),i("info"),i("success"),i("warning"),i("error")])])])]),e("vertical",{flexDirection:"column"},[s("button",[o("&:first-child:not(:last-child)",`
 margin-bottom: ${t};
 margin-left: ${t};
 margin-right: ${t};
 border-bottom-left-radius: ${t};
 border-bottom-right-radius: ${t};
 `),o("&:last-child:not(:first-child)",`
 margin-top: ${t};
 margin-left: ${t};
 margin-right: ${t};
 border-top-left-radius: ${t};
 border-top-right-radius: ${t};
 `),o("&:not(:first-child):not(:last-child)",`
 margin: ${t};
 border-radius: ${t};
 `),n("default"),e("ghost",[n("primary"),n("info"),n("success"),n("warning"),n("error")])])])]),x={size:{type:String,default:void 0},vertical:Boolean},B=f({name:"ButtonGroup",props:x,setup(r){const{mergedClsPrefixRef:d,mergedRtlRef:c}=p(r);return b("-button-group",y,d),g(m,r),{rtlEnabled:h("ButtonGroup",c,d),mergedClsPrefix:d}},render(){const{mergedClsPrefix:r}=this;return $("div",{class:[`${r}-button-group`,this.rtlEnabled&&`${r}-button-group--rtl`,this.vertical&&`${r}-button-group--vertical`],role:"group"},this.$slots)}});export{B as _};
