(() => {
 'use strict';
 const root=document.documentElement,key='wm_theme:'+root.dataset.themeOwner,pref=root.dataset.themePreference||'system',baseline=key+':preference';
 const valid=t=>['system','light','dark'].includes(t);
 const paint=t=>{root.dataset.theme=valid(t)?t:'system';document.querySelectorAll('#theme-toggle,#theme-toggle-guest').forEach(b=>b.setAttribute('aria-pressed',String(isDark())));};
 const isDark=()=>root.dataset.theme==='dark'||(root.dataset.theme==='system'&&matchMedia('(prefers-color-scheme: dark)').matches);
 const set=t=>{if(!valid(t))return;paint(t);try{localStorage.setItem(key,t);localStorage.setItem(baseline,pref);}catch{}};
 let initial=pref;try{if(localStorage.getItem(baseline)===pref&&valid(localStorage.getItem(key)))initial=localStorage.getItem(key);else localStorage.removeItem(key);localStorage.setItem(baseline,pref);}catch{}
 window.WorkTheme={set,toggle:()=>set(isDark()?'light':'dark')};paint(initial);
 document.addEventListener('DOMContentLoaded',()=>paint(root.dataset.theme));
 matchMedia('(prefers-color-scheme: dark)').addEventListener('change',()=>paint(root.dataset.theme));
 window.addEventListener('storage',e=>{if(e.key===key)paint(valid(e.newValue)?e.newValue:pref);});
})();
