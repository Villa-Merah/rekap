import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';

const source=readFileSync('public/omni-login.js','utf8');
async function start(){
    const listeners={};
    const navigations=[];
    const button={dataset:{prepare:'/omni/prepare'},addEventListener:(type,fn)=>{listeners[type]=fn;},disabled:false};
    const status={textContent:''};
    const popup={closed:false,close(){this.closed=true;},location:{replace(){}}};
    const window={addEventListener:(type,fn)=>{listeners[type]=fn;},open:()=>popup,location:{origin:'https://rekap.sivmi.id',assign:url=>navigations.push(url)}};
    const document={getElementById:id=>id==='omni-login'?button:status,querySelector:()=>({content:'csrf-token'})};
    vm.runInNewContext(source,{window,document,URL,crypto:{randomUUID:()=> 'unique-window'},fetch:async()=>({ok:true,json:async()=>({authorization_url:'https://sivmi.id/omni/authorize',callback_url:'https://rekap.sivmi.id/omni/callback',server_origin:'https://sivmi.id',state:'session-state'})}),setInterval:()=>1,clearInterval(){},Date});
    await listeners.click();
    return {listeners,navigations,popup};
}

test('Only the opened SSO window and expected origin may complete login',async()=>{
    const h=await start();
    const data={source:'omni_sso',redirect_url:'https://rekap.sivmi.id/omni/callback?sso_data=proof'};
    h.listeners.message({origin:'https://evil.example',source:h.popup,data});
    h.listeners.message({origin:'https://sivmi.id',source:{},data});
    assert.equal(h.navigations.length,0);
    h.listeners.message({origin:'https://sivmi.id',source:h.popup,data});
    const destination=new URL(h.navigations[0]);
    assert.equal(destination.origin,'https://rekap.sivmi.id');
    assert.equal(destination.pathname,'/omni/callback');
    assert.equal(destination.searchParams.get('state'),'session-state');
    assert.equal(destination.searchParams.get('sso_data'),'proof');
    assert.equal(h.popup.closed,true);
});

test('An external or different callback cannot redirect the local window',async()=>{
    const h=await start();
    for(const url of ['https://evil.example/omni/callback?sso_data=proof','https://rekap.sivmi.id/wrong?sso_data=proof']){
        h.listeners.message({origin:'https://sivmi.id',source:h.popup,data:{source:'omni_sso',redirect_url:url}});
    }
    assert.equal(h.navigations.length,0);
});
