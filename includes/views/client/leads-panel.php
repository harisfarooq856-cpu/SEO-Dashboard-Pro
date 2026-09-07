<?php
/**
 * Shared Leads tab panel for dashboard.php
 * Requires: $rid (int), $ld_pfx (string: 'cl' or 'tm')
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$ld_all = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM " . SEO_Dash_Database::$data_leads . " WHERE report_id = %d AND trashed = 0 ORDER BY id ASC",
    $rid
), ARRAY_A );
$ld_total = count( $ld_all );
$ld_kpi   = ['new'=>0,'contacted'=>0,'checking'=>0,'qualified'=>0,'converted'=>0,'lost'=>0];
foreach ( $ld_all as $lr ) {
    $ls = strtolower( $lr['status'] ?: 'new' );
    if ( isset( $ld_kpi[$ls] ) ) $ld_kpi[$ls]++;
}
$ld_conv_pct = $ld_total > 0 ? round( $ld_kpi['converted'] / $ld_total * 100 ) : 0;
$uid = 'ld_' . $ld_pfx;

?>
<style>
.seo-ld-status-wrap{position:relative;display:inline-flex;align-items:center;}
.seo-ld-sel{appearance:none;-webkit-appearance:none;border-radius:20px;padding:4px 30px 4px 11px;font-size:12px;font-weight:700;cursor:pointer;outline:none;border:1px solid currentColor;font-family:inherit;transition:background .15s,color .15s;}
.seo-ld-sel-arr{position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:8px;opacity:.8;line-height:1;}
.seo-ld-msg-btn,.seo-ld-note-btn{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid var(--cc-border);background:var(--cc-surf2);color:var(--cc-primary);transition:all .15s;white-space:nowrap;}
.seo-ld-msg-btn:hover{background:var(--cc-primary);color:#fff;border-color:var(--cc-primary);}
.seo-ld-note-btn{color:var(--cc-muted);}
.seo-ld-note-btn:hover{background:#f59e0b;color:#fff;border-color:#f59e0b;}
.seo-ld-note-btn.has-note{color:#f59e0b;border-color:#f59e0b44;background:#f59e0b11;}
.seo-ld-modal-wrap{display:none;position:fixed;inset:0;background:rgba(15,23,42,.72);z-index:99999;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px);}
.seo-ld-modal-wrap.open{display:flex;}
.seo-ld-modal{background:var(--cc-surf);border:1px solid var(--cc-border);border-radius:16px;padding:28px;width:100%;max-width:480px;box-shadow:0 20px 60px rgba(0,0,0,.25);display:flex;flex-direction:column;gap:16px;}
.seo-ld-modal-hd{display:flex;align-items:center;justify-content:space-between;}
.seo-ld-modal-hd h4{margin:0;font-size:16px;font-weight:700;color:var(--cc-text);}
.seo-ld-modal-close{width:32px;height:32px;border-radius:50%;border:none;background:var(--cc-surf2);color:var(--cc-subtle);cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;transition:all .15s;}
.seo-ld-modal-close:hover{background:var(--cc-border);color:var(--cc-text);}
.seo-ld-modal-body{font-size:14px;color:var(--cc-text);line-height:1.7;white-space:pre-wrap;word-break:break-word;background:var(--cc-surf2);border-radius:10px;padding:14px 16px;}
.seo-ld-note-ta{width:100%;box-sizing:border-box;border-radius:10px;border:1px solid var(--cc-border);background:var(--cc-surf2);color:var(--cc-text);font-size:14px;padding:12px 14px;font-family:inherit;resize:vertical;min-height:100px;outline:none;transition:border .15s;}
.seo-ld-note-ta:focus{border-color:var(--cc-primary);}
.seo-ld-save-btn{padding:9px 22px;border-radius:8px;border:none;background:var(--cc-primary);color:#fff;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:opacity .15s;}
.seo-ld-save-btn:hover{opacity:.88;}
.seo-ld-save-btn:disabled{opacity:.5;cursor:not-allowed;}
.seo-ld-save-ok{font-size:12px;color:#10b981;font-weight:600;display:none;}
/* Email & phone columns */
.seo-ld-contact-cell { color: var(--cc-subtle); }
.seo-client-app.seo-dark .seo-ld-contact-cell { color: #000 !important; }
</style>

<div class="seo-cl-panel">
    <div class="seo-cl-panel-hd"><h3>🎯 Leads</h3></div>

    <!-- KPI Cards -->
    <div style="padding:20px 20px 16px;">
        <div style="display:flex;flex-wrap:wrap;gap:12px;">
            <?php
            $kpi_cards = [
                ['💼','TOTAL LEADS','All time','#f97316',$ld_total,false],
                ['🔠','NEW','Awaiting contact','#8b5cf6',$ld_kpi['new'],true],
                ['📞','CONTACTED','In progress','#06b6d4',$ld_kpi['contacted'],false],
                ['✅','QUALIFIED','Ready to convert','#10b981',$ld_kpi['qualified'],false],
                ['🎉','CONVERTED',$ld_conv_pct.'% of total','#059669',$ld_kpi['converted'],false],
                ['❌','LOST','Not converted','#ef4444',$ld_kpi['lost'],false],
            ];
            foreach ($kpi_cards as $kc) :
                [$ico,$lbl,$sub,$col,$val,$badge] = $kc;
            ?>
            <div style="flex:1;min-width:130px;background:var(--cc-surf2);border:1px solid var(--cc-border);border-radius:14px;padding:18px 20px;display:flex;flex-direction:column;align-items:flex-start;gap:6px;border-top:3px solid <?php echo $col;?>;position:relative;">
                <?php if($badge):?><div style="position:absolute;top:12px;right:14px;background:<?php echo $col;?>;color:#fff;font-size:9px;font-weight:800;padding:2px 7px;border-radius:20px;letter-spacing:.5px;">NEW</div><?php endif;?>
                <div style="font-size:26px;line-height:1;"><?php echo $ico;?></div>
                <div style="font-size:28px;font-weight:800;color:var(--cc-text);line-height:1.1;"><?php echo $val;?></div>
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--cc-subtle);"><?php echo $lbl;?></div>
                <div style="font-size:11px;color:<?php echo $col;?>;font-weight:600;"><?php echo $sub;?></div>
            </div>
            <?php endforeach;?>
        </div>
    </div>

    <!-- Charts -->
    <div id="<?php echo $uid;?>-charts" style="padding:0 20px 20px;display:flex;flex-wrap:wrap;gap:16px;">
        <!-- Donut chart: by status -->
        <div style="flex:1;min-width:220px;background:var(--cc-surf2);border:1px solid var(--cc-border);border-radius:14px;padding:18px 20px;">
            <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--cc-subtle);margin-bottom:14px;">Leads by Status</div>
            <canvas id="<?php echo $uid;?>-chart-status" height="180"></canvas>
        </div>
        <!-- Bar chart: status breakdown -->
        <div style="flex:2;min-width:280px;background:var(--cc-surf2);border:1px solid var(--cc-border);border-radius:14px;padding:18px 20px;">
            <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--cc-subtle);margin-bottom:14px;">Status Breakdown</div>
            <canvas id="<?php echo $uid;?>-chart-bar" height="180"></canvas>
        </div>
    </div>

    <script>
    (function(){
        var _donut=null,_bar=null;
        var _uid='<?php echo $uid;?>';
        var _pfx='<?php echo $ld_pfx;?>';

        var _counts = {
            new:       <?php echo intval($ld_kpi['new']);?>,
            contacted: <?php echo intval($ld_kpi['contacted']);?>,
            checking:  <?php echo intval($ld_kpi['checking']);?>,
            qualified: <?php echo intval($ld_kpi['qualified']);?>,
            converted: <?php echo intval($ld_kpi['converted']);?>,
            lost:      <?php echo intval($ld_kpi['lost']);?>
        };

        var _labels = ['New','Contacted','Checking','Qualified','Converted','Lost'];
        var _keys   = ['new','contacted','checking','qualified','converted','lost'];
        var _colors = ['#8b5cf6','#06b6d4','#f59e0b','#10b981','#059669','#ef4444'];

        function initCharts(){
            var ctxD = document.getElementById(_uid+'-chart-status');
            var ctxB = document.getElementById(_uid+'-chart-bar');
            if(!ctxD||!ctxB||typeof Chart==='undefined') return;
            var vals = _keys.map(function(k){return _counts[k];});

            _donut = new Chart(ctxD,{
                type:'doughnut',
                data:{labels:_labels,datasets:[{data:vals,backgroundColor:_colors,borderWidth:2,borderColor:'transparent',hoverOffset:6}]},
                options:{cutout:'68%',plugins:{legend:{position:'right',labels:{font:{size:11},boxWidth:12,padding:10}}},animation:{duration:500}}
            });

            _bar = new Chart(ctxB,{
                type:'bar',
                data:{labels:_labels,datasets:[{label:'Leads',data:vals,backgroundColor:_colors,borderRadius:8,borderSkipped:false,barThickness:26}]},
                options:{indexAxis:'y',plugins:{legend:{display:false}},scales:{x:{beginAtZero:true,ticks:{stepSize:1,font:{size:11}},grid:{color:'rgba(148,163,184,.12)'}},y:{ticks:{font:{size:11}},grid:{display:false}}},animation:{duration:500}}
            });
        }

        window['seoLdUpdateCharts_'+_pfx] = function(oldStatus, newStatus){
            if(oldStatus===newStatus) return;
            if(_counts[oldStatus]!==undefined && _counts[oldStatus]>0) _counts[oldStatus]--;
            if(_counts[newStatus]!==undefined) _counts[newStatus]++;
            var vals=_keys.map(function(k){return _counts[k];});
            if(_donut){_donut.data.datasets[0].data=vals;_donut.update();}
            if(_bar){_bar.data.datasets[0].data=vals;_bar.update();}
            if(typeof window.seoLdUpdateCharts==='function') window.seoLdUpdateCharts(oldStatus, newStatus);
        };

        function tryInit(){
            if(typeof Chart!=='undefined'){ initCharts(); return; }
            // Load Chart.js then init
            if(!document.getElementById('seo-ld-chartjs-cdn')){
                var s=document.createElement('script');
                s.id='seo-ld-chartjs-cdn';
                s.src='https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js';
                s.onload=function(){ initCharts(); };
                document.head.appendChild(s);
            } else {
                // Script tag exists but not loaded yet — wait
                var t=setInterval(function(){ if(typeof Chart!=='undefined'){clearInterval(t);initCharts();} },80);
            }
        }

        if(document.readyState==='loading'){
            document.addEventListener('DOMContentLoaded', tryInit);
        } else {
            tryInit();
        }
    })();
    </script>

    <!-- Pag Top -->
    <div id="<?php echo $uid;?>-pag-top" style="display:flex;justify-content:flex-end;padding:10px 24px;border-top:1px solid var(--cc-border);border-bottom:1px solid var(--cc-border);"></div>

    <!-- Table -->
    <div class="seo-cl-table-wrap">
    <?php if(empty($ld_all)):?>
        <div style="text-align:center;padding:48px;color:var(--cc-subtle);">
            <div style="font-size:36px;margin-bottom:12px;">🎯</div>
            <h4 style="margin:0 0 8px;color:var(--cc-text);">No Leads Yet</h4>
            <p style="margin:0;font-size:13px;">Your agency will add leads here.</p>
        </div>
    <?php else:?>
        <table class="seo-cl-table" id="<?php echo $uid;?>-table">
            <thead><tr>
                <th style="width:40px;text-align:center;">#</th>
                <th>Name</th><th>Phone</th><th>Email</th>
                <th style="min-width:120px;">Message</th>
                <th style="min-width:170px;">Status</th>
                <th style="min-width:140px;">Notes</th>
            </tr></thead>
            <tbody id="<?php echo $uid;?>-tbody">
            <?php
            $ls_colors = ['new'=>'#8b5cf6','contacted'=>'#06b6d4','qualified'=>'#10b981','converted'=>'#059669','lost'=>'#ef4444','checking'=>'#f59e0b'];
            $ctr=0;
            foreach($ld_all as $lr):
                $ctr++;
                $ls  = strtolower($lr['status']?:'new');
                $lc  = $ls_colors[$ls]??'#94a3b8';
                $hm  = !empty(trim($lr['message']??''));
                $hn  = !empty(trim($lr['notes']??''));
            ?>
            <tr class="seo-ld-r-<?php echo $ld_pfx;?>" data-id="<?php echo intval($lr['id']);?>" style="display:none;"><?php /* JS init() shows first 20 */ ?>
                <td style="text-align:center;color:var(--cc-subtle);font-size:12px;"><?php echo $ctr;?></td>
                <td style="font-weight:600;"><?php echo esc_html($lr['name']?:'—');?></td>
                <td class="seo-ld-contact-cell"><?php echo esc_html($lr['phone']?:'—');?></td>
                <td class="seo-ld-contact-cell"><?php echo esc_html($lr['email']?:'—');?></td>
                <td>
                    <?php if($hm):?>
                    <button class="seo-ld-msg-btn" data-pfx="<?php echo $ld_pfx;?>" data-msg="<?php echo esc_attr($lr['message']);?>">👁 View</button>
                    <?php else:?><span style="color:var(--cc-subtle);font-size:12px;">—</span><?php endif;?>
                </td>
                <td>
                    <div class="seo-ld-status-wrap">
                        <select class="seo-ld-sel seo-ld-st-<?php echo $ld_pfx;?>"
                            data-id="<?php echo intval($lr['id']);?>"
                            data-prev-status="<?php echo esc_attr($ls);?>"
                            style="background:<?php echo $lc;?>18;color:<?php echo $lc;?>;border-color:<?php echo $lc;?>55;">
                            <option value="new"       <?php selected($ls,'new');?>>🔠 New</option>
                            <option value="contacted" <?php selected($ls,'contacted');?>>📞 Contacted</option>
                            <option value="checking"  <?php selected($ls,'checking');?>>🔍 Checking</option>
                            <option value="qualified" <?php selected($ls,'qualified');?>>✅ Qualified</option>
                            <option value="converted" <?php selected($ls,'converted');?>>🎉 Converted</option>
                            <option value="lost"      <?php selected($ls,'lost');?>>❌ Lost</option>
                        </select>
                        <span class="seo-ld-sel-arr" style="color:<?php echo $lc;?>;">▼</span>
                    </div>
                </td>
                <td>
                    <div style="display:flex;gap:6px;align-items:center;flex-wrap:nowrap;">
                    <?php if($hn):?>
                    <button class="seo-ld-note-btn has-note seo-ld-note-view-btn"
                        data-pfx="<?php echo $ld_pfx;?>"
                        data-id="<?php echo intval($lr['id']);?>"
                        data-note="<?php echo esc_attr($lr['notes']??'');?>"
                        data-mode="view">
                        👁 View
                    </button>
                    <button class="seo-ld-note-btn has-note seo-ld-note-edit-btn"
                        data-pfx="<?php echo $ld_pfx;?>"
                        data-id="<?php echo intval($lr['id']);?>"
                        data-note="<?php echo esc_attr($lr['notes']??'');?>"
                        data-mode="edit">
                        📝 Edit
                    </button>
                    <?php else:?>
                    <button class="seo-ld-note-btn seo-ld-note-edit-btn"
                        data-pfx="<?php echo $ld_pfx;?>"
                        data-id="<?php echo intval($lr['id']);?>"
                        data-note=""
                        data-mode="edit">
                        ➕ Add note
                    </button>
                    <?php endif;?>
                    </div>
                </td>
            </tr>
            <?php endforeach;?>
            </tbody>
        </table>
    <?php endif;?>
    </div>

    <!-- Pag Bot -->
    <div id="<?php echo $uid;?>-pag-bot" style="display:flex;justify-content:center;align-items:center;gap:10px;padding:20px;border-top:1px solid var(--cc-border);"></div>
</div>

<!-- Message Modal -->
<div class="seo-ld-modal-wrap" id="<?php echo $uid;?>-msg-modal">
    <div class="seo-ld-modal">
        <div class="seo-ld-modal-hd">
            <h4>💬 Message</h4>
            <button class="seo-ld-modal-close" onclick="document.getElementById('<?php echo $uid;?>-msg-modal').classList.remove('open')">✕</button>
        </div>
        <div class="seo-ld-modal-body" id="<?php echo $uid;?>-msg-body"></div>
    </div>
</div>

<!-- Notes Modal -->
<div class="seo-ld-modal-wrap" id="<?php echo $uid;?>-note-modal">
    <div class="seo-ld-modal">
        <div class="seo-ld-modal-hd">
            <h4 id="<?php echo $uid;?>-note-modal-title">📝 Notes</h4>
            <button class="seo-ld-modal-close" onclick="document.getElementById('<?php echo $uid;?>-note-modal').classList.remove('open')">✕</button>
        </div>
        <div class="seo-ld-modal-body" id="<?php echo $uid;?>-note-view-body" style="display:none;"></div>
        <textarea class="seo-ld-note-ta" id="<?php echo $uid;?>-note-ta" placeholder="Type your note here…" rows="5" style="display:none;"></textarea>
        <div style="display:flex;align-items:center;gap:12px;" id="<?php echo $uid;?>-note-edit-row">
            <button class="seo-ld-save-btn" id="<?php echo $uid;?>-note-save">Save Note</button>
            <span class="seo-ld-save-ok" id="<?php echo $uid;?>-note-ok">✓ Saved!</span>
        </div>
    </div>
</div>

<script>
(function(){
    var pfx='<?php echo $ld_pfx;?>',uid='ld_<?php echo $ld_pfx;?>';
    var _pg=1,_pp=20,_rows=[],_noteId=0,_noteTr=null;

    function init(){
        var tb=document.getElementById(uid+'-tbody');
        if(!tb)return;
        _rows=Array.from(tb.querySelectorAll('tr.seo-ld-r-'+pfx));
        filter();
    }
    function filter(){
        var tot=_rows.length,tp=Math.max(1,Math.ceil(tot/_pp));
        if(_pg>tp)_pg=tp;
        _rows.forEach(function(r,i){r.style.display=(i>=(_pg-1)*_pp&&i<_pg*_pp)?'':'none';});
        renderPag(tot,tp);
    }
    function renderPag(tot,tp){
        var t=document.getElementById(uid+'-pag-top'),b=document.getElementById(uid+'-pag-bot');
        if(!t||!b)return;
        if(tp<=1){t.innerHTML='';b.innerHTML='';return;}
        function mk(){
            var h='<div style="display:flex;align-items:center;gap:6px;">';
            h+='<span style="font-size:12px;color:var(--cc-text);margin-right:12px;">Page '+_pg+' of '+tp+' ('+tot+' leads)</span>';
            var d1=_pg===1?' disabled':'',d2=_pg===tp?' disabled':'';
            h+='<button class="seo-bk-page-btn" onclick="seoLdGo_'+pfx+'(1)"'+d1+'>«</button>';
            h+='<button class="seo-bk-page-btn" onclick="seoLdGo_'+pfx+'('+(_pg-1)+')"'+d1+'>‹</button>';
            for(var p=Math.max(1,_pg-2);p<=Math.min(tp,_pg+2);p++){
                h+=p===_pg?'<button class="seo-bk-page-btn active" disabled>'+p+'</button>':'<button class="seo-bk-page-btn" onclick="seoLdGo_'+pfx+'('+p+')">'+p+'</button>';
            }
            h+='<button class="seo-bk-page-btn" onclick="seoLdGo_'+pfx+'('+(_pg+1)+')"'+d2+'>›</button>';
            h+='<button class="seo-bk-page-btn" onclick="seoLdGo_'+pfx+'('+tp+')"'+d2+'>»</button>';
            return h+'</div>';
        }
        t.innerHTML=mk();b.innerHTML=mk();
    }
    window['seoLdGo_'+pfx]=function(p){
        _pg=p;filter();
        var tbl=document.getElementById(uid+'-table');
        if(tbl)tbl.scrollIntoView({behavior:'smooth',block:'start'});
    };

    // Close modals on backdrop click
    document.addEventListener('click',function(e){
        if(e.target&&e.target.classList.contains('seo-ld-modal-wrap'))e.target.classList.remove('open');
    });

    // Message view
    document.addEventListener('click',function(e){
        var btn=e.target.closest('.seo-ld-msg-btn');
        if(!btn||btn.getAttribute('data-pfx')!==pfx)return;
        var bd=document.getElementById(uid+'-msg-body');
        if(bd)bd.textContent=btn.getAttribute('data-msg')||'';
        document.getElementById(uid+'-msg-modal').classList.add('open');
    });

    // Notes open
    document.addEventListener('click',function(e){\
        var btn=e.target.closest('.seo-ld-note-btn');
        if(!btn||btn.getAttribute('data-pfx')!==pfx)return;
        _noteId=parseInt(btn.getAttribute('data-id'),10);
        _noteTr=btn.closest('tr');
        var mode=btn.getAttribute('data-mode')||'edit';
        var note=btn.getAttribute('data-note')||'';
        var ta=document.getElementById(uid+'-note-ta');
        var vb=document.getElementById(uid+'-note-view-body');
        var er=document.getElementById(uid+'-note-edit-row');
        var title=document.getElementById(uid+'-note-modal-title');
        var ok=document.getElementById(uid+'-note-ok');
        if(ok)ok.style.display='none';
        if(mode==='view'){
            if(title)title.textContent='📝 View Note';
            if(vb){vb.textContent=note;vb.style.display='block';}
            if(ta)ta.style.display='none';
            if(er)er.style.display='none';
        } else {
            if(title)title.textContent='📝 Notes';
            if(vb)vb.style.display='none';
            if(ta){ta.value=note;ta.style.display='block';}
            if(er)er.style.display='flex';
        }
        var m=document.getElementById(uid+'-note-modal');
        if(m){m.classList.add('open');if(mode==='edit'&&ta)setTimeout(function(){ta.focus();},80);}
    });

    // Notes save
    var saveBtn=document.getElementById(uid+'-note-save');
    if(saveBtn)saveBtn.addEventListener('click',function(){
        var ta=document.getElementById(uid+'-note-ta');
        var ok=document.getElementById(uid+'-note-ok');
        var notes=ta?ta.value:'';
        var ajax=(typeof seoDashClient!=='undefined')?seoDashClient.ajax:'';
        var nonce=(typeof seoDashClient!=='undefined')?seoDashClient.nonce:'';
        var rid=(typeof seoDashClient!=='undefined')?seoDashClient.report_id:0;
        if(!ajax||!nonce||!_noteId)return;
        saveBtn.disabled=true;saveBtn.textContent='Saving…';
        var fd=new FormData();
        fd.append('action','seo_dash_client_save_lead_notes');
        fd.append('nonce',nonce);fd.append('row_id',_noteId);
        fd.append('notes',notes);fd.append('report_id',rid);
        fetch(ajax,{method:'POST',body:fd,credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(r){
            saveBtn.disabled=false;saveBtn.textContent='Save Note';
            if(r.success){
                if(ok){ok.style.display='inline';setTimeout(function(){ok.style.display='none';},2500);}
                if(_noteTr){
                    var nb=_noteTr.querySelector('.seo-ld-note-btn');
                    var td=nb?nb.closest('td'):null;
                    if(td){
                        if(notes.trim()){
                            td.innerHTML='<div style="display:flex;gap:6px;align-items:center;flex-wrap:nowrap;"><button class="seo-ld-note-btn has-note seo-ld-note-view-btn" data-pfx="'+pfx+'" data-id="'+_noteId+'" data-note="'+notes.replace(/"/g,'&quot;')+'" data-mode="view">👁 View</button><button class="seo-ld-note-btn has-note seo-ld-note-edit-btn" data-pfx="'+pfx+'" data-id="'+_noteId+'" data-note="'+notes.replace(/"/g,'&quot;')+'" data-mode="edit">📝 Edit</button></div>';
                        } else {
                            td.innerHTML='<div style="display:flex;gap:6px;align-items:center;"><button class="seo-ld-note-btn seo-ld-note-edit-btn" data-pfx="'+pfx+'" data-id="'+_noteId+'" data-note="" data-mode="edit">➕ Add note</button></div>';
                        }
                    }
                }
                setTimeout(function(){document.getElementById(uid+'-note-modal').classList.remove('open');},900);
            }
        })
        .catch(function(){saveBtn.disabled=false;saveBtn.textContent='Save Note';});
    });

    // Status change
    document.addEventListener('change',function(e){
        var sel=e.target;
        if(!sel||!sel.classList.contains('seo-ld-st-'+pfx))return;
        var oldStatus=sel.getAttribute('data-prev-status')||sel.value;
        var lid=sel.getAttribute('data-id'),status=sel.value;
        var ajax=(typeof seoDashClient!=='undefined')?seoDashClient.ajax:'';
        var nonce=(typeof seoDashClient!=='undefined')?seoDashClient.nonce:'';
        var rid=(typeof seoDashClient!=='undefined')?seoDashClient.report_id:0;
        if(!ajax||!nonce)return;
        var clrs={new:'#8b5cf6',contacted:'#06b6d4',checking:'#f59e0b',qualified:'#10b981',converted:'#059669',lost:'#ef4444'};
        var c=clrs[status]||'#94a3b8';
        sel.style.background=c+'18';sel.style.color=c;sel.style.borderColor=c+'55';
        var w=sel.closest('.seo-ld-status-wrap');
        if(w){var a=w.querySelector('.seo-ld-sel-arr');if(a)a.style.color=c;}
        // Update charts
        if(typeof window['seoLdUpdateCharts_'+pfx]==='function') window['seoLdUpdateCharts_'+pfx](oldStatus,status);
        sel.setAttribute('data-prev-status',status);
        var fd=new FormData();
        fd.append('action','seo_dash_client_update_lead_status');
        fd.append('nonce',nonce);fd.append('row_id',lid);
        fd.append('status',status);fd.append('report_id',rid);
        fetch(ajax,{method:'POST',body:fd,credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(r){if(!r.success)console.warn('Lead status failed',r);})
        .catch(function(e){console.error(e);});
    });

    // Init
    if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',init);}else{init();}
    document.querySelectorAll('.seo-cl-nav-btn[data-tab="leads"]').forEach(function(b){
        b.addEventListener('click',function(){setTimeout(init,60);});
    });
})();
</script>
