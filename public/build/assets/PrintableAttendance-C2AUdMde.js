import{b as g,c as f,d as t,H as V,f as k,t as p,q as Y,e as b,F as N,y as D,D as S,B,N as rt,V as ot,z as it,h as st,r as L}from"./app-OvlpdaT0.js";import{f as at}from"./printPreview-DQFRcr1D.js";import{u as lt}from"./AppSettingStore-DhBedrJJ.js";import{_ as dt}from"./_plugin-vue_export-helper-DlAUqK2U.js";const mt=["src"],pt={class:"dtr-two-company"},ut={key:1,class:"dtr-two-department"},ct={key:2,class:"dtr-two-address"},gt={class:"dtr-two-info"},ft={class:"dtr-two-name"},ht={class:"dtr-two-month"},yt={class:"dtr-two-dated"},wt={class:"dtr-two-employee"},bt={class:"dtr-two-footer"},xt={class:"dtr-two-approved"},vt={class:"dtr-two-signature"},zt=["src"],St={class:"dtr-two-keeper-signature"},kt=["src"],Nt={__name:"DtrTemplateTwo",props:{user:Object,layoutSettings:{type:Object,default:()=>({})},companyName:String,headerAddress:String,companyLogo:String,monthYear:String,days:Array,recordValue:Function,undertimeValue:Function,timekeeperName:String,timekeeperSignature:String,signatoryName:String,signatorySignature:String},setup(i){const v=i,O=S(()=>{const h=v.layoutSettings,n=(y,c)=>{const u=Number(h[`biometricDtrTemplate2${y}`]??c);return Number.isFinite(u)?Math.min(18,Math.max(1,u)):c};return{"--dtr-republic-size":`${n("RepublicFontSize",8)}pt`,"--dtr-department-size":`${n("DepartmentFontSize",8)}pt`,"--dtr-address-size":`${n("AddressFontSize",8)}pt`,"--dtr-title-size":`${n("TitleFontSize",13)}pt`,"--dtr-name-size":`${n("NameFontSize",10)}pt`,"--dtr-company-size":`${n("CompanyFontSize",8)}pt`,"--dtr-approved-size":`${n("ApprovedFontSize",10)}pt`,"--dtr-timekeeper-size":`${n("TimekeeperFontSize",10)}pt`,"--dtr-month-size":`${n("MonthFontSize",10)}pt`,"--dtr-table-content-size":`${n("TableContentFontSize",7)}pt`,"--dtr-table-header-size":`${n("TableHeaderFontSize",7)}pt`,"--dtr-employee-size":`${n("EmployeeFontSize",10)}pt`}}),z=S(()=>{const h=v.layoutSettings.biometricDtrTemplate2LogoPosition;return h==="center"?"top":["top","left","right","bottom"].includes(h)?h:"left"}),_=S(()=>{const h={Left:0,Right:4,Top:0,Bottom:3},n=c=>{const u=Number(v.layoutSettings[`biometricDtrTemplate2Logo${c}`]??h[c]);return Number.isFinite(u)?Math.max(0,Math.min(30,u)):h[c]},y=c=>{const u=Number(v.layoutSettings[`biometricDtrTemplate2Logo${c}`]??15);return`${Number.isFinite(u)?Math.max(1,Math.min(50,u)):15}mm`};return{translate:`${n("Right")-n("Left")}mm ${n("Bottom")-n("Top")}mm`,width:y("Width"),height:y("Height")}}),T=S(()=>{var h,n,y,c,u,w,F;return((n=(h=v.user)==null?void 0:h.department_ref)==null?void 0:n.department_name)||((c=(y=v.user)==null?void 0:y.departmentRef)==null?void 0:c.department_name)||(typeof((u=v.user)==null?void 0:u.department)=="string"?v.user.department:(F=(w=v.user)==null?void 0:w.department)==null?void 0:F.department_name)||""}),A=(h,n)=>{const y=n(h,"hrs"),c=n(h,"min");return y||c?`${y||"0"}:${String(c||"0").padStart(2,"0")}`:""};return(h,n)=>{var y,c;return g(),f("article",{class:Y(["dtr-two",`dtr-two-logo-${z.value}`]),style:V(O.value)},[t("header",{class:Y(["dtr-two-header",{"dtr-two-header-with-logo":i.companyLogo}])},[i.companyLogo?(g(),f("img",{key:0,src:i.companyLogo,style:V(_.value),alt:"Company logo"},null,12,mt)):k("",!0),n[0]||(n[0]=t("div",{class:"dtr-two-republic"},"Republic of the Philippines",-1)),t("strong",pt,p(i.companyName),1),T.value?(g(),f("strong",ut,p(T.value),1)):k("",!0),i.headerAddress?(g(),f("div",ct,p(i.headerAddress),1)):k("",!0)],2),n[17]||(n[17]=t("h1",null,"DAILY TIME RECORD",-1)),t("div",gt,[t("span",ft,[n[1]||(n[1]=b("Name: ",-1)),t("strong",null,p((y=i.user)==null?void 0:y.name),1)]),t("span",ht,[n[2]||(n[2]=b("For the Month of: ",-1)),t("strong",null,p(i.monthYear),1)])]),t("table",null,[t("colgroup",null,[n[3]||(n[3]=t("col",{style:{width:"6%"}},null,-1)),(g(),f(N,null,D(6,u=>t("col",{key:u,style:{width:"9%"}})),64)),n[4]||(n[4]=t("col",{style:{width:"8%"}},null,-1)),n[5]||(n[5]=t("col",{style:{width:"8%"}},null,-1)),n[6]||(n[6]=t("col",{style:{width:"24%"}},null,-1))]),t("thead",null,[n[7]||(n[7]=t("tr",null,[t("th",{rowspan:"2"},"DATE"),t("th",{colspan:"2"},"AM"),t("th",{colspan:"2"},"PM"),t("th",{colspan:"2"},[b("OVER"),t("wbr"),b("TIME")]),t("th",{rowspan:"2"},[b("UNDER"),t("br"),b("TIME")]),t("th",{rowspan:"2"},[b("OVER"),t("br"),b("TIME")]),t("th",{rowspan:"2"},"REMARKS")],-1)),t("tr",null,[(g(),f(N,null,D(["IN","OUT","IN","OUT","IN","OUT"],(u,w)=>t("th",{key:w},p(u),1)),64))])]),t("tbody",null,[(g(!0),f(N,null,D(i.days,u=>(g(),f("tr",{key:u},[t("td",null,p(u),1),(g(),f(N,null,D(["am_in","am_out","pm_in","pm_out"],w=>t("td",{key:w},p(i.recordValue(u,w)),1)),64)),n[8]||(n[8]=t("td",null,null,-1)),n[9]||(n[9]=t("td",null,null,-1)),t("td",null,p(A(u,i.undertimeValue)),1),n[10]||(n[10]=t("td",null,null,-1)),n[11]||(n[11]=t("td",null,null,-1))]))),128)),n[12]||(n[12]=t("tr",{class:"dtr-two-total"},[t("td",{colspan:"10"},"TOTAL")],-1))])]),n[18]||(n[18]=t("p",{class:"dtr-two-certification"},"I CERTIFY on my honor that the above statement is correct and that the services were applied to the public service.",-1)),t("p",yt,"Dated at "+p(T.value||i.companyName)+" on, __________________",1),t("div",wt,[t("strong",null,p((c=i.user)==null?void 0:c.name),1),n[13]||(n[13]=t("div",null,"Signature",-1))]),t("div",bt,[t("div",xt,[n[14]||(n[14]=t("p",null,[t("strong",null,"Approved:"),b(),t("em",null,"as certified and verified")],-1)),t("div",vt,[i.signatorySignature?(g(),f("img",{key:0,src:i.signatorySignature,alt:"Signatory e-signature"},null,8,zt)):k("",!0),t("strong",null,p(i.signatoryName),1)])]),t("div",null,[n[15]||(n[15]=t("p",{class:"dtr-two-certification"},"I CERTIFY ON MY HONOR that the record of the hour of work was made daily at the time of arrival and departure from office and at beginning and ending of works.",-1)),t("div",St,[i.timekeeperSignature?(g(),f("img",{key:0,src:i.timekeeperSignature,alt:"Time keeper e-signature"},null,8,kt)):k("",!0),t("strong",null,p(i.timekeeperName),1)]),n[16]||(n[16]=t("div",{class:"dtr-two-keeper"},"Time Keeper",-1))])])],6)}}},Tt=`.dtr-two { padding: 3mm; background: white; color: black; font-family: Arial, sans-serif; font-size: 8pt; }\r
.dtr-two-header { min-height: 12mm; position: relative; text-align: center; padding: 0 13mm; line-height: 1.3; }\r
.dtr-two-header img { position: absolute; left: 4mm; top: 0; width: 15mm; height: 15mm; object-fit: contain; }\r
.dtr-two-header strong { display: block; }\r
.dtr-two h1 { text-align: center; font-size: var(--dtr-title-size, 13pt); margin: 3mm 0; font-weight: bold; }\r
.dtr-two-info { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 3mm; margin: 4mm 0; font-size: 7pt; }\r
.dtr-two-info strong { border-bottom: 1px solid black; }\r
.dtr-two table { width: 100%; border-collapse: collapse; table-layout: fixed; }\r
.dtr-two th, .dtr-two td { border: 0.25mm solid black; text-align: center; padding: 0.35mm 0.1mm; font-size: 6pt; line-height: 1.15; overflow-wrap: anywhere; }\r
/* Keep all six time columns equal; rows expand with the chosen font size. */\r
.dtr-two th { font-weight: bold; overflow-wrap: normal; word-break: normal; vertical-align: middle; padding: 0.45em 0.2em; }\r
.dtr-two thead th[rowspan] { white-space: nowrap; }\r
.dtr-two td { padding: 0.2em 0.15em; }\r
.dtr-two tbody td:first-child { white-space: nowrap; }\r
.dtr-two tbody tr { height: 3.35mm; }\r
.dtr-two .dtr-two-total { height: auto; }\r
.dtr-two-total td { text-align: left; }\r
.dtr-two-certification, .dtr-two-dated { font-style: italic; font-size: 7pt; line-height: 1.2; margin: 1mm 0; }\r
.dtr-two-employee { width: 48%; margin: 3mm 0 1mm auto; text-align: center; font-size: 7pt; }\r
.dtr-two-employee strong { display: block; border-bottom: 1px solid black; }\r
.dtr-two-employee div { font-style: italic; }\r
.dtr-two-footer { display: grid; grid-template-columns: 42% 48%; justify-content: space-between; font-size: 7pt; }\r
.dtr-two-approved { display: flex; flex-direction: column; justify-content: flex-end; }\r
.dtr-two-signature { height: 10mm; border-bottom: 1px solid black; display: flex; align-items: center; justify-content: flex-end; flex-direction: column; position: relative; }\r
.dtr-two-signature img { position: absolute; bottom: 2mm; max-height: 9mm; max-width: 30mm; object-fit: contain; }\r
.dtr-two-signature strong { position: relative; }\r
.dtr-two-keeper-signature { height: 7mm; position: relative; display: flex; align-items: center; justify-content: flex-end; flex-direction: column; text-align: center; }\r
.dtr-two-keeper-signature img { position: absolute; bottom: 2mm; max-height: 9mm; max-width: 30mm; object-fit: contain; }\r
.dtr-two-keeper-signature strong { position: relative; }\r
.dtr-two-keeper { margin-top: 0; border-top: 1px solid black; text-align: center; font-style: italic; }\r
\r
/* Variables live on the article so the isolated print document keeps customization. */\r
.dtr-two .dtr-two-company { font-size: var(--dtr-company-size, 8pt); overflow-wrap: anywhere; }\r
.dtr-two .dtr-two-name { font-size: var(--dtr-name-size, 10pt); }\r
.dtr-two .dtr-two-approved { font-size: var(--dtr-approved-size, 10pt); }\r
.dtr-two .dtr-two-keeper-signature, .dtr-two .dtr-two-keeper { font-size: var(--dtr-timekeeper-size, 10pt); }\r
.dtr-two .dtr-two-employee strong { font-size: var(--dtr-employee-size, 10pt); }\r
.dtr-two-logo-right .dtr-two-header img { left: auto; right: 4mm; }\r
.dtr-two-logo-top .dtr-two-header-with-logo { padding-top: 13mm; }\r
.dtr-two-logo-top .dtr-two-header img { left: 50%; transform: translateX(-50%); }\r
\r
.dtr-two .dtr-two-month { font-size: var(--dtr-month-size, 10pt); }\r
.dtr-two thead th { font-size: var(--dtr-table-header-size, 7pt); font-weight: 700; }\r
\r
.dtr-two-logo-bottom .dtr-two-header-with-logo { padding-bottom: 13mm; }\r
.dtr-two-logo-bottom .dtr-two-header img { left: 50%; top: auto; bottom: 0; transform: translateX(-50%); }\r
\r
.dtr-two .dtr-two-republic { font-size: var(--dtr-republic-size, 8pt); overflow-wrap: anywhere; }\r
\r
.dtr-two .dtr-two-department { font-size: var(--dtr-department-size, 8pt); overflow-wrap: anywhere; }\r
\r
.dtr-two .dtr-two-address { font-size: var(--dtr-address-size, 8pt); overflow-wrap: anywhere; }\r
\r
.dtr-two tbody td { font-size: var(--dtr-table-content-size, 7pt); font-weight: 400; }\r
`,Mt=`
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; background: white; color: black; }
  @page { size: A4 landscape; margin: 5mm; }
  .page-wrapper {
    display: flex;
    align-items: flex-start;
    width: 287mm;
    gap: 3mm;
    break-inside: avoid;
    page-break-inside: avoid;
  }
  .page-wrapper + .page-wrapper { break-before: page; page-break-before: always; }
  .form-copy { width: 142mm; flex: 0 0 142mm; break-inside: avoid; }
  ${Tt}
`,$t={class:"space-y-3"},Dt={key:0,class:"flex flex-col justify-between gap-3 border border-slate-700 bg-[linear-gradient(135deg,_#0f172a_0%,_#1e293b_40%,_#0f766e_100%)] px-4 py-3 text-white md:flex-row md:items-center print:hidden"},_t={class:"flex min-w-0 items-center gap-3"},At={class:"min-w-0"},Ct={class:"mt-0.5 text-[11px] text-slate-300"},Ft={class:"flex flex-wrap items-center gap-x-3 gap-y-2"},Ot={class:"flex cursor-pointer items-center gap-1.5 whitespace-nowrap text-[11px] font-medium text-slate-200"},Rt={class:"flex items-center gap-1.5"},Et=["value"],It={style:{"margin-top":"10px","border-bottom":"2px solid #111827",position:"relative"}},Lt={key:0,style:{position:"absolute",top:"0",right:"0",display:"flex","padding-right":"10px","justify-content":"center","align-items":"center","margin-bottom":"4px"}},Ut=["src"],Pt={style:{"text-align":"center","font-size":"6pt",color:"#4b5563",margin:"0",padding:"0"}},jt={style:{"text-align":"center","font-size":"6pt",color:"#4b5563","margin-top":"8pt"}},Ht={style:{"text-align":"center","font-size":"12pt","font-weight":"700",color:"#111827","margin-top":"5px"}},Vt={style:{display:"grid","grid-template-columns":"1fr 1fr","column-gap":"16px","row-gap":"10px","margin-bottom":"16px","font-size":"14px"}},Yt={style:{"line-height":"8pt","grid-column":"span 2",display:"flex","align-items":"center","margin-top":"10px"}},Bt={style:{"font-weight":"600","text-align":"center",color:"#111827",width:"100%","font-size":"10pt","line-height":"1.2","border-bottom":"1px solid #111827"}},qt={style:{overflow:"visible","margin-bottom":"20px"}},Kt={style:{width:"100%","table-layout":"fixed","border-collapse":"collapse",border:"1px solid #111827","font-size":"14px"}},Wt={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center",color:"#111827"}},Xt={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},Gt={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},Jt={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},Qt={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},Zt={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},te={style:{border:"1px solid #111827","font-size":"6pt","text-align":"center"}},ee={style:{color:"#374151"}},ne={style:{"margin-top":"32px"}},re={style:{"margin-left":"auto",width:"50%","text-align":"center"}},oe={key:0,style:{display:"flex","justify-content":"center","margin-bottom":"6px"}},ie=["src"],se={style:{"font-size":"7pt","text-transform":"uppercase"}},ae=`
                    * { box-sizing: border-box; margin: 0; padding: 0; }
                    body { font-family: Arial, sans-serif; background: white; color: black; }

                    @page {
                        size: 13in 8.5in landscape;
                        margin: 0;
                    }

                    .page-wrapper {
                        display: flex;
                        flex-direction: row;
                        align-items: flex-start;
                        width: 330.2mm;
                    }

                    .form-copy {
                        width: 82.55mm;
                        height: 215.9mm;
                        overflow: hidden;
                        page-break-inside: avoid;
                        flex-shrink: 0;
                        padding: 4px 3px 0 3px;
                    }

                    /* Horizontal cut line between rows */
                    .cut-line-h {
                        width: 330.2mm;
                        margin:  0;
                        border-top: 1px dashed #666;
                        text-align: center;
                        position: relative;
                    }
                    .cut-line-h span {
                        position: absolute;
                        top: -7px;
                        left: 50%;
                        transform: translateX(-50%);
                        background: white;
                        padding: 0px;
                        font-size: 7px;
                        color: #555;
                        letter-spacing: 1px;
                    }

                    h1 { font-size: 10px; font-weight: bold; }
                    p { font-size: 7px; }
                    table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
                    th, td { border: 1px solid black; padding: 1.5px 2px; text-align: center; font-size: 6.5px; }
                    thead tr { background-color: #e5e7eb; }
                    th { font-weight: bold; }
                    .text-center { text-align: center; }
                    .text-left { text-align: left; }
                    .text-right { text-align: right; }
                    .font-bold { font-weight: bold; }
                    .font-semibold { font-weight: 600; }
                    .italic { font-style: italic; }
                    .grid { display: grid; }
                    .grid-cols-2 { grid-template-columns: 1fr 1fr; gap: 3px; margin-bottom: 4px; }
                    .grid-cols-3 { grid-template-columns: 1fr 1fr 1fr; gap: 6px; margin-top: 6px; }
                    .mb-1 { margin-bottom: 1px; }
                    .mb-2 { margin-bottom: 2px; }
                    .mb-6 { margin-bottom: 4px; }
                    .mb-8 { margin-bottom: 5px; }
                    .mt-8 { margin-top: 6px; }
                    .pb-4 { padding-bottom: 3px; }
                    .border-b-2 { border-bottom: 2px solid black; }
                    .border-t { border-top: 1px solid black; }
                    .h-12 { height: 14px; display: block; }
                    .text-xs { font-size: 6px; }
                    .text-sm { font-size: 7px; }
                    .text-2xl { font-size: 11px; }
                    .space-y-4 > * + * { margin-top: 4px; }
                    .overflow-x-auto { overflow: visible; }
                    .p-8 { padding: 5px; }
                    .p-2 { padding: 1.5px; }
                    .rounded-lg, .rounded { border-radius: 0; }
                    .space-y-4 { display: block; }
`,le={__name:"PrintableAttendance",props:{settingsOverride:{type:Object,default:null},user:Object,selectedYear:Number,selectedMonth:Number,attendanceRecords:Array,companyName:{type:String,default:"Biometric System"},companyLogo:{type:String,default:""},showLogo:{type:Boolean,default:!1},showControls:{type:Boolean,default:!0},signatoryName:{type:String,default:""},signatorySignature:{type:String,default:""},signatorySignatureEnabled:{type:Boolean,default:!1},calculateUndertime:{type:Boolean,default:void 0},overrides:{type:Array,default:()=>[]}},setup(i,{expose:v}){const O=lt(),z=S(()=>n.settingsOverride??O),_=S(()=>z.value.biometricDtrTemplate==="template2"),T=L(null),A=L(4),h=L(!1),n=i,y=S(()=>typeof n.calculateUndertime=="boolean"?n.calculateUndertime:h.value),c=S(()=>!n.selectedMonth||!n.selectedYear?"N/A":new Date(n.selectedYear,n.selectedMonth-1).toLocaleDateString("en-US",{month:"long",year:"numeric"})),u=S(()=>{if(!n.selectedMonth||!n.selectedYear)return[];const r=new Date(n.selectedYear,n.selectedMonth,0).getDate();return Array.from({length:r},(e,s)=>s+1)}),w=r=>{if(!r)return null;if(r instanceof Date){const o=r.getTime();return Number.isNaN(o)?null:r.getHours()*60+r.getMinutes()}const e=String(r).trim();if(!e)return null;const s=e.match(/^(\d{1,2}):(\d{2})(?::\d{2})?\s*([AaPp][Mm])?$/);if(s){let o=Number(s[1]);const m=Number(s[2]),x=(s[3]||"").toUpperCase();return Number.isNaN(o)||Number.isNaN(m)||(x==="AM"?o===12&&(o=0):x==="PM"&&o<12&&(o+=12),o<0||o>23||m<0||m>59)?null:o*60+m}const l=e.split(":");if(l.length<2)return null;const d=Number(l[0]),a=Number(l[1]);return Number.isNaN(d)||Number.isNaN(a)?null:d*60+a},F=r=>{if(!Number.isFinite(r)||r<=0)return"";const e=Math.floor(r/60),s=r%60;return{hours:e,minutes:s}},q=()=>{var d,a;const r=((d=n.user)==null?void 0:d.office_shift)||((a=n.user)==null?void 0:a.officeShift),s=(Array.isArray(r==null?void 0:r.schedules)?[...r.schedules]:[]).sort((o,m)=>(o.sequence||0)-(m.sequence||0));if(!s.length)return null;const l=s.reduce((o,m)=>{const x=w(m==null?void 0:m.time_in),$=w(m==null?void 0:m.time_out);return x===null||$===null||$<=x?o:o+($-x)},0);return l>0?l:null},K=r=>{const e=w(r==null?void 0:r.am_in),s=w(r==null?void 0:r.am_out),l=w(r==null?void 0:r.pm_in),d=w(r==null?void 0:r.pm_out);let a=0;return e!==null&&s!==null&&s>e&&(a+=s-e),l!==null&&d!==null&&d>l&&(a+=d-l),a>0?a:null},W=r=>{var d,a;const e=String(r).padStart(2,"0"),s=String(n.selectedMonth).padStart(2,"0"),l=`${n.selectedYear}-${s}-${e}`;return((a=(d=n.attendanceRecords)==null?void 0:d.find)==null?void 0:a.call(d,o=>o.date===l))||null},X=r=>{const e=new Date(r);return Number.isNaN(e.getTime())?"":e.toLocaleTimeString("en-US",{hour:"2-digit",minute:"2-digit",hour12:!0})},G=r=>{const e=new Date(r);if(Number.isNaN(e.getTime()))return null;const s=e.getFullYear(),l=String(e.getMonth()+1).padStart(2,"0"),d=String(e.getDate()).padStart(2,"0");return`${s}-${l}-${d}`},J=r=>{const e=String((r==null?void 0:r.new_checktype)||"").trim().toUpperCase(),s=new Date(r==null?void 0:r.new_checktime);if(Number.isNaN(s.getTime()))return null;const l=s.getHours();return e==="I"?l<12?"am_in":"pm_in":e==="O"?l<=12?"am_out":"pm_out":null},U=(r,e)=>{if(!Array.isArray(n.overrides)||!n.overrides.length)return"";const s=String(r).padStart(2,"0"),l=String(n.selectedMonth).padStart(2,"0"),d=`${n.selectedYear}-${l}-${s}`,a=n.overrides.filter(o=>G(o==null?void 0:o.new_checktime)===d).filter(o=>J(o)===e).sort((o,m)=>{const x=new Date((o==null?void 0:o.updated_at)||(o==null?void 0:o.created_at)||(o==null?void 0:o.new_checktime)).getTime();return new Date((m==null?void 0:m.updated_at)||(m==null?void 0:m.created_at)||(m==null?void 0:m.new_checktime)).getTime()-x});return a.length?X(a[0].new_checktime):""},Q=r=>{const e=W(r);if(!e)return null;const s=["am_in","am_out","pm_in","pm_out"],l={...e};return s.forEach(d=>{const a=U(r,d);a&&(l[d]=a)}),l},M=(r,e)=>{if(!n.attendanceRecords||!Array.isArray(n.attendanceRecords))return"";if(e==="am_in"||e==="am_out"||e==="pm_in"||e==="pm_out"){const o=U(r,e);if(o)return o}const s=String(r).padStart(2,"0"),l=String(n.selectedMonth).padStart(2,"0"),d=`${n.selectedYear}-${l}-${s}`,a=n.attendanceRecords.find(o=>o.date===d);if(!a)return"";switch(e){case"am_in":return a.am_in||"";case"am_out":return a.am_out||"";case"pm_in":return a.pm_in||"";case"pm_out":return a.pm_out||"";case"undertime_hrs":return a.undertimeHrs||"";case"undertime_min":return a.undertimeMin||"";default:return""}},R=(r,e)=>{const s=()=>{const x=M(r,"undertime_hrs"),$=M(r,"undertime_min"),C=String(x||"").trim(),E=String($||"").trim(),I=Number(C),j=Number(E),H=C!==""&&!Number.isNaN(I)&&I>0,nt=E!==""&&!Number.isNaN(j)&&j>0;return!H&&!nt?"":e==="hrs"?H?String(I):"":E};if(!y.value)return s();const l=Q(r);if(!l)return"";const d=q(),a=K(l);if(d===null||a===null)return s();const o=Math.max(0,d-a);if(o<=0)return"";const m=F(o);return m?e==="hrs"?String(m.hours):String(m.minutes).padStart(2,"0"):""},Z=()=>{var r;return((r=T.value)==null?void 0:r.innerHTML)||""},tt=(r,e)=>{const s=e||1,l=_.value?2:4;let d="";for(let a=0;a<s;a+=l){const o=[];for(let C=0;C<l;C++)a+C<s&&o.push(`<div class="form-copy">${r}</div>`);const m=o.join(""),x=a+l>=s;d+=`<div class="page-wrapper">${m}</div>`}return d},P=(r=A.value||1)=>{var s;const e=(s=T.value)==null?void 0:s.innerHTML;return e?{bodyHtml:tt(e,r),styles:_.value?Mt:ae,formsPerPage:_.value?2:4}:null},et=()=>{const r=P(A.value||1);if(!r)return;const e=window.open("","_blank");if(!e){window.alert("Allow pop-ups to open the print preview.");return}e.document.write(`
    <!DOCTYPE html>
    <html>
      <head>
        <meta charset="UTF-8" />
        <title>Daily Time Record</title>
        <style>
                    ${r.styles}
        </style>
      </head>
      <body>
                ${r.bodyHtml}
      </body>
    </html>
  `),e.document.close(),at(e,{closeAfterPrint:!0})};return v({getPrintPayload:P,getPrintContent:Z}),(r,e)=>{var s,l,d,a;return g(),f("div",$t,[i.showControls?(g(),f("div",Dt,[t("div",_t,[e[5]||(e[5]=t("div",{class:"flex h-8 w-8 shrink-0 items-center justify-center border border-white/15 bg-white/5 text-cyan-200"},[t("svg",{class:"h-4 w-4",viewBox:"0 0 24 24",fill:"none",stroke:"currentColor","stroke-width":"1.8","stroke-linejoin":"round","aria-hidden":"true"},[t("path",{d:"M6 9V3h12v6M6 17H4a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2"}),t("path",{d:"M6 14h12v7H6zM17 12h1"})])],-1)),t("div",At,[e[4]||(e[4]=t("h3",{class:"text-sm font-semibold text-white"},"Daily Time Record",-1)),t("p",Ct,[b(p(c.value)+" ",1),e[2]||(e[2]=t("span",{class:"mx-1 text-teal-300"},"·",-1)),e[3]||(e[3]=b(" Print preview",-1))])])]),t("div",Ft,[t("label",Ot,[B(t("input",{"onUpdate:modelValue":e[0]||(e[0]=o=>h.value=o),type:"checkbox",class:"h-3.5 w-3.5 border-white/30 bg-slate-900 text-teal-500 focus:ring-teal-400 focus:ring-offset-slate-900"},null,512),[[rt,h.value]]),e[6]||(e[6]=b(" Calculate Undertime ",-1))]),t("div",Rt,[e[7]||(e[7]=t("label",{for:"dtr-copies",class:"whitespace-nowrap text-[11px] font-medium text-slate-300"},"Copies",-1)),B(t("select",{id:"dtr-copies","onUpdate:modelValue":e[1]||(e[1]=o=>A.value=o),class:"h-7 border border-white/20 bg-slate-900 py-0 pl-2 pr-7 text-[11px] font-semibold text-white focus:border-teal-400 focus:outline-none focus:ring-1 focus:ring-teal-400"},[(g(),f(N,null,D(10,o=>t("option",{key:o,value:o},p(o),9,Et)),64))],512),[[ot,A.value,void 0,{number:!0}]])]),t("button",{type:"button",onClick:et,class:"inline-flex h-7 items-center gap-1.5 border border-cyan-300/30 bg-[linear-gradient(135deg,_#0891b2_0%,_#0f766e_100%)] px-3 text-[11px] font-semibold text-white shadow-sm transition hover:bg-[linear-gradient(135deg,_#0e7490_0%,_#115e59_100%)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300/50"},[...e[8]||(e[8]=[t("svg",{class:"h-3.5 w-3.5",viewBox:"0 0 24 24",fill:"none",stroke:"currentColor","stroke-width":"1.8","stroke-linejoin":"round","aria-hidden":"true"},[t("path",{d:"M6 9V3h12v6M6 17H4a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2"}),t("path",{d:"M6 14h12v7H6z"})],-1),b(" Print Record ",-1)])])])])):k("",!0),t("div",{ref_key:"printContainer",ref:T,style:{background:"white",padding:"0px",border:"1px solid #d1d5db","border-radius":"0",color:"#111827"}},[_.value?(g(),it(Nt,{key:0,user:i.user,"company-name":i.companyName,"company-logo":i.showLogo?i.companyLogo:"","header-address":z.value.biometricDtrHeaderAddress,"layout-settings":z.value,"month-year":c.value,days:u.value,"record-value":M,"undertime-value":R,"timekeeper-name":z.value.biometricDtrTimekeeperUseDefault?z.value.biometricDtrTimekeeperName:"","timekeeper-signature":z.value.biometricDtrTimekeeperUseDefault&&z.value.biometricDtrTimekeeperSignatureEnabled?z.value.biometricDtrTimekeeperSignature:"","signatory-name":i.signatoryName,"signatory-signature":i.signatorySignatureEnabled?i.signatorySignature:""},null,8,["user","company-name","company-logo","header-address","layout-settings","month-year","days","timekeeper-name","timekeeper-signature","signatory-name","signatory-signature"])):(g(),f(N,{key:1},[t("div",It,[i.showLogo&&i.companyLogo?(g(),f("div",Lt,[t("img",{src:i.companyLogo,alt:"Company logo",style:{"max-height":"50px","object-fit":"contain"}},null,8,Ut)])):k("",!0),e[9]||(e[9]=t("p",{style:{"font-size":"8px","font-weight":"600",color:"#374151","margin-bottom":"4px","padding-left":"10px"}},"CSC Form No. 48",-1)),e[10]||(e[10]=t("h1",{style:{"text-align":"center","font-size":"8pt","font-weight":"700",color:"#111827",margin:"0",padding:"0"}}," DAILY TIME RECORD",-1)),t("p",Pt,p(i.companyName||"Company / School Name"),1),t("p",jt,p(((s=i.user)==null?void 0:s.department)||((d=(l=i.user)==null?void 0:l.department_ref)==null?void 0:d.department_name)||""),1),t("h1",Ht,p((a=i.user)==null?void 0:a.name),1)]),t("div",Vt,[t("div",Yt,[e[11]||(e[11]=t("p",{style:{color:"#4b5563","font-size":"7.5pt","white-space":"nowrap","padding-right":"5px"}},"For the Month of",-1)),t("p",Bt,p(c.value),1)]),e[12]||(e[12]=st('<div style="line-height:2pt;display:flex;align-items:center;" data-v-3cff9619><p style="color:#4b5563;font-size:7.5pt;" data-v-3cff9619>Official Hours</p><p style="font-weight:600;color:#111827;" data-v-3cff9619></p></div><div style="line-height:8pt;display:flex;align-items:center;" data-v-3cff9619><span style="color:#4b5563;font-size:7.5pt;white-space:nowrap;padding-right:5px;" data-v-3cff9619> Regular Days </span><span style="flex:1;font-weight:600;color:#111827;border-bottom:1px solid #111827;" data-v-3cff9619></span></div><div style="line-height:8pt;display:flex;align-items:center;" data-v-3cff9619><p style="color:#4b5563;font-size:7.5pt;" data-v-3cff9619>Arrival and Departure</p><p style="font-weight:600;color:#111827;border-bottom:1px solid #111827;" data-v-3cff9619></p></div><div style="line-height:8pt;display:flex;align-items:center;" data-v-3cff9619><p style="color:#4b5563;font-size:7.5pt;padding-right:10px;" data-v-3cff9619>Saturdays</p><p style="width:100%;font-weight:600;color:#111827;border-bottom:1px solid #111827;" data-v-3cff9619></p></div>',4))]),t("div",qt,[t("table",Kt,[t("colgroup",null,[e[13]||(e[13]=t("col",{style:{width:"10%"}},null,-1)),(g(),f(N,null,D(6,o=>t("col",{key:o,style:{width:"15%"}})),64))]),e[15]||(e[15]=t("thead",null,[t("tr",{style:{background:"#f3f4f6"}},[t("th",{rowspan:"2",style:{"font-size":"8.5pt",border:"1px solid #111827",padding:"8px 0","text-align":"center","vertical-align":"middle","white-space":"nowrap","font-weight":"700",color:"#111827"}}," DAY "),t("th",{colspan:"2",style:{"font-size":"8.5pt",border:"1px solid #111827",padding:"8px","text-align":"center","font-weight":"700",color:"#111827"}}," A.M. "),t("th",{colspan:"2",style:{"font-size":"8.5pt",border:"1px solid #111827",padding:"8px","text-align":"center","font-weight":"700",color:"#111827"}}," P.M. "),t("th",{colspan:"2",style:{"font-size":"8.5pt",border:"1px solid #111827",padding:"8px","text-align":"center","font-weight":"700",color:"#111827"}}," UNDERTIME ")]),t("tr",{style:{background:"#f3f4f6"}},[t("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," IN"),t("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," OUT"),t("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," IN"),t("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," OUT"),t("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," Hrs."),t("th",{style:{"font-weight":"300",border:"1px solid #111827","text-align":"center",color:"#111827","font-size":"8.5pt"}}," Min.")])],-1)),t("tbody",null,[(g(!0),f(N,null,D(u.value,o=>(g(),f("tr",{key:o},[t("td",Wt,p(o),1),t("td",Xt,p(M(o,"am_in")),1),t("td",Gt,p(M(o,"am_out")),1),t("td",Jt,p(M(o,"pm_in")),1),t("td",Qt,p(M(o,"pm_out")),1),t("td",Zt,p(R(o,"hrs")),1),t("td",te,p(R(o,"min")),1)]))),128)),e[14]||(e[14]=t("tr",{style:{background:"#f3f4f6","font-weight":"700"}},[t("td",{colspan:"7",style:{border:"1px solid #111827",padding:"8px","text-align":"left",color:"#111827"}}," TOTAL ")],-1))])])]),t("div",ee,[e[20]||(e[20]=t("p",{style:{"font-style":"italic","margin-bottom":"16px","font-size":"8pt"}},"     I CERTIFY on my honor that the above is a true and correct report of the hours of work performed, a record of which was made daily at the time of arrival at and departure from office. ",-1)),t("div",ne,[e[18]||(e[18]=t("div",{style:{"margin-left":"auto",width:"50%","text-align":"center"}},[t("div",{style:{"border-top":"1px solid #111827"}})],-1)),e[19]||(e[19]=t("div",{style:{}},[t("p",{style:{"font-style":"italic","margin-bottom":"16px","font-size":"8pt"}},"     Verified as to the prescribed office hours. ")],-1)),t("div",re,[i.signatorySignatureEnabled&&i.signatorySignature?(g(),f("div",oe,[t("img",{src:i.signatorySignature,alt:"Signatory e-signature",style:{"max-height":"40px","max-width":"160px","object-fit":"contain"}},null,8,ie)])):k("",!0),t("p",se,p(i.signatoryName),1),e[16]||(e[16]=t("div",{style:{"border-top":"1px solid #111827"}},null,-1)),e[17]||(e[17]=t("p",{style:{color:"#111827","margin-top":"0px","font-style":"italic","font-size":"8pt"}},"In-Charge",-1))])])])],64))],512)])}}},ce=dt(le,[["__scopeId","data-v-3cff9619"]]);export{ce as P};
