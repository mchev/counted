import{_ as i}from"./AppLayout-5727bca0.js";import o from"./DeleteTeamForm-7c74f750.js";import{S as r}from"./SectionBorder-d1761ea8.js";import l from"./TeamMemberManager-c8f1b99c.js";import n from"./UpdateTeamNameForm-6d46e732.js";import{o as m,c,w as s,a,b as t,d as p,F as d,e as f}from"./app-c5666b27.js";import"./_plugin-vue_export-helper-c27b6911.js";import"./Modal-20c5583a.js";import"./SectionTitle-0cfe77cc.js";import"./ConfirmationModal-73e471e7.js";import"./DangerButton-0341eaf6.js";import"./SecondaryButton-7257d6a4.js";import"./ActionMessage-92d039a8.js";import"./DialogModal-9f2f990a.js";import"./FormSection-4483f1b5.js";import"./TextInput-03aee476.js";import"./InputLabel-4906c731.js";import"./PrimaryButton-d3b6f492.js";const u=a("h2",{class:"font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight"}," Team Settings ",-1),x={class:"max-w-7xl mx-auto py-10 sm:px-6 lg:px-8"},D={__name:"Show",props:{team:Object,availableRoles:Array,permissions:Object},setup(e){return(b,g)=>(m(),c(i,{title:"Team Settings"},{header:s(()=>[u]),default:s(()=>[a("div",null,[a("div",x,[t(n,{team:e.team,permissions:e.permissions},null,8,["team","permissions"]),t(l,{class:"mt-10 sm:mt-0",team:e.team,"available-roles":e.availableRoles,"user-permissions":e.permissions},null,8,["team","available-roles","user-permissions"]),e.permissions.canDeleteTeam&&!e.team.personal_team?(m(),p(d,{key:0},[t(r),t(o,{class:"mt-10 sm:mt-0",team:e.team},null,8,["team"])],64)):f("",!0)])])]),_:1}))}};export{D as default};
