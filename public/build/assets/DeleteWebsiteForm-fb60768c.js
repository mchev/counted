import{r as f,T as w,o as y,c as h,w as e,f as s,a as n,b as o,t as x,u as a,q as W,n as g}from"./app-31d3a126.js";import{_ as k}from"./Modal-1928622b.js";import{_}from"./DangerButton-2251f79c.js";import{_ as v}from"./DialogModal-f40b6bd4.js";import{_ as D}from"./InputError-2f805f1e.js";import{_ as C}from"./SecondaryButton-e395ebd4.js";import{_ as V}from"./TextInput-c34320d9.js";import"./SectionTitle-2cab51fc.js";import"./_plugin-vue_export-helper-c27b6911.js";const $=n("div",{class:"max-w-xl text-sm text-gray-600 dark:text-gray-400"}," Once the website is deleted, all of its resources and data will be permanently deleted. Before deleting the website, please download any data or information that you wish to retain. ",-1),N={class:"mt-5"},B=n("p",{class:"mb-2"},"Are you sure you want to delete the website? Once the website is deleted, all of its resources and data will be permanently deleted.",-1),K={class:"mt-4"},A={__name:"DeleteWebsiteForm",props:{website:Object},setup(c){const m=c,l=f(!1),i=f(null),t=w({name:m.website.name,name_confirmation:""}),p=()=>{l.value=!0,setTimeout(()=>i.value.focus(),250)},d=()=>{t.delete(route("websites.destroy",m.website),{preserveScroll:!0,onSuccess:()=>r(),onError:()=>i.value.focus(),onFinish:()=>t.reset()})},r=()=>{l.value=!1,t.reset()};return(O,u)=>(y(),h(k,null,{title:e(()=>[s(" Delete Website ")]),description:e(()=>[s(" Permanently delete the website. ")]),content:e(()=>[$,n("div",N,[o(_,{onClick:p},{default:e(()=>[s(" Delete Website ")]),_:1})]),o(v,{show:l.value,onClose:r},{title:e(()=>[s(" Delete Website ")]),content:e(()=>[B,n("p",null,"Please enter the website name ("+x(c.website.name)+") to confirm you would like to permanently delete it.",1),n("div",K,[o(V,{ref_key:"WebsiteNameInput",ref:i,modelValue:a(t).name_confirmation,"onUpdate:modelValue":u[0]||(u[0]=b=>a(t).name_confirmation=b),type:"text",class:"mt-1 block w-3/4",placeholder:"Website name",autocomplete:"current-password",onKeyup:W(d,["enter"])},null,8,["modelValue","onKeyup"]),o(D,{message:a(t).errors.name,class:"mt-2"},null,8,["message"])])]),footer:e(()=>[o(C,{onClick:r},{default:e(()=>[s(" Cancel ")]),_:1}),o(_,{class:g(["ml-3",{"opacity-25":a(t).processing}]),disabled:a(t).processing,onClick:d},{default:e(()=>[s(" Delete Website ")]),_:1},8,["class","disabled"])]),_:1},8,["show"])]),_:1}))}};export{A as default};