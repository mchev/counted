import{o as t,d as l,a as e,F as c,g as d,z as p,u as m,t as i}from"./app-db92b856.js";const f=e("div",{class:"flex justify-between mb-4 font-semibold"},[e("h3",null,"Pages"),e("p",null,"Views")],-1),_={class:"flex py-2 my-1 w-full relative text-sm"},b={class:"relative z-10 pl-4"},g={class:"w-20 text-right"},y={__name:"PageStats",props:{pages:Object},setup(a){const u=Object.values(a.pages).reduce((o,n)=>o+n,0);return(o,n)=>(t(),l(c,null,[f,e("ul",null,[(t(!0),l(c,null,d(a.pages,(r,s)=>(t(),l("li",{key:s,class:"flex justify-between"},[e("div",_,[e("div",{class:"absolute block top-0 bottom-0 z-0 bg-teal-800 rounded-r-sm",style:p(`width:${r/m(u)*100}%`)},null,4),e("div",b,i(s||"Undefined"),1)]),e("div",g,i(r),1)]))),128))])],64))}};export{y as default};