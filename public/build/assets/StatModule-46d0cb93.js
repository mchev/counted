import{_}from"./FormattedNumber-a1ce4950.js";import{o as e,d as s,a as t,t as r,u as d,F as u,g as m,z as f,b as p}from"./app-4da22f6e.js";const h={class:"flex justify-between mb-4 font-semibold"},b=t("p",null,"Visitors",-1),v={key:0,class:"max-h-80 overflow-y-auto pr-4"},y={class:"flex py-2 my-1 w-full relative text-sm"},g={class:"relative z-10 pl-4"},k={class:"w-20 text-right"},w={key:1},x=t("p",null,"No data",-1),j=[x],N={__name:"StatModule",props:{title:String,data:Object},setup(a){const o=Object.values(a.data).reduce((i,n)=>i+n,0);return(i,n)=>(e(),s(u,null,[t("div",h,[t("h3",null,r(a.title),1),b]),d(o)>0?(e(),s("ul",v,[(e(!0),s(u,null,m(a.data,(c,l)=>(e(),s("li",{key:l,class:"flex justify-between"},[t("div",y,[t("div",{class:"absolute block top-0 bottom-0 z-0 bg-teal-900 bg-opacity-50 rounded-r",style:f(`width:${c/d(o)*100}%`)},null,4),t("div",g,r(l||"Undefined"),1)]),t("div",k,[p(_,{value:c},null,8,["value"])])]))),128))])):(e(),s("div",w,j))],64))}};export{N as default};
