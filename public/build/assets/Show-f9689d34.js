import{_ as c}from"./AppLayout-aaabdd8c.js";import p from"./DeleteUserForm-6778455b.js";import l from"./LogoutOtherBrowserSessionsForm-1885a6e0.js";import{S as s}from"./SectionBorder-130e0b44.js";import f from"./TwoFactorAuthenticationForm-f8e7234f.js";import u from"./UpdatePasswordForm-92781498.js";import d from"./UpdateProfileInformationForm-bdc97326.js";import{o,c as _,w as n,a as i,d as r,b as t,e as a,F as h}from"./app-33e24b48.js";import"./_plugin-vue_export-helper-c27b6911.js";import"./Modal-378386ee.js";import"./SectionTitle-80b44706.js";import"./DangerButton-aa47b9ac.js";import"./DialogModal-d93c4bbc.js";import"./TextInput-42ce1338.js";import"./SecondaryButton-ac8e7fca.js";import"./ActionMessage-e7f8c11a.js";import"./PrimaryButton-b0a8d55f.js";import"./InputLabel-6503eca9.js";import"./FormSection-f78e0788.js";const g=i("h2",{class:"font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight"}," Profile ",-1),$={class:"max-w-7xl mx-auto py-10 sm:px-6 lg:px-8"},k={key:0},w={key:1},y={key:2},G={__name:"Show",props:{confirmsTwoFactorAuthentication:Boolean,sessions:Array},setup(m){return(e,x)=>(o(),_(c,{title:"Profile"},{header:n(()=>[g]),default:n(()=>[i("div",null,[i("div",$,[e.$page.props.jetstream.canUpdateProfileInformation?(o(),r("div",k,[t(d,{user:e.$page.props.auth.user},null,8,["user"]),t(s)])):a("",!0),e.$page.props.jetstream.canUpdatePassword?(o(),r("div",w,[t(u,{class:"mt-10 sm:mt-0"}),t(s)])):a("",!0),e.$page.props.jetstream.canManageTwoFactorAuthentication?(o(),r("div",y,[t(f,{"requires-confirmation":m.confirmsTwoFactorAuthentication,class:"mt-10 sm:mt-0"},null,8,["requires-confirmation"]),t(s)])):a("",!0),t(l,{sessions:m.sessions,class:"mt-10 sm:mt-0"},null,8,["sessions"]),e.$page.props.jetstream.hasAccountDeletionFeatures?(o(),r(h,{key:3},[t(s),t(p,{class:"mt-10 sm:mt-0"})],64)):a("",!0)])])]),_:1}))}};export{G as default};