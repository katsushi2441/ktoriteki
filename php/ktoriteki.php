<?php
/**
 * Kurage 取適法チェック（ktoriteki）― 1ファイルPHP。
 *
 * 2026年1月1日施行の取適法（中小受託取引適正化法・旧 下請法）について、
 *   ① その取引が対象になるか（取引の内容＋資本金・従業員の規模要件）
 *   ② 4つの義務と11の禁止行為のうち、気をつけるところはどこか（発注する側／受ける側）
 * を、質問に答えるだけで確かめる。判定はブラウザの中だけで行い、入力はサーバーへ送らない。
 *
 *   /ktoriteki.php/         入口（対象判定と自己点検）
 *   /ktoriteki.php/gimu/    4つの義務と11の禁止行為・改正点
 *   /ktoriteki.php/about    出典・判定のしかた・しないこと
 *   /ktoriteki.php/sitemap.xml /robots.txt /llms.txt
 *
 * 出典（2026-10-05 確認）
 *   公正取引委員会「委託事業者の義務」https://www.jftc.go.jp/toriteki/toritekigaiyo/oyagimu.html
 *   公正取引委員会 中部事務所「中小受託取引適正化法（取適法）について」（令和8年3月9日）
 *     https://wwwtb.mlit.go.jp/chubu/jidosya/ouensaito/shiryou/20260309/20260309_05.pdf
 * 法律の助言ではない。個別の判断は公正取引委員会・中小企業庁の相談窓口か弁護士へ。
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Tokyo');

const SITE = 'https://kurage.exbridge.jp';
const BASE = SITE . '/ktoriteki.php';
const NAME = 'Kurage 取適法チェック';
const CHECKED = '2026-10-05';
const SRC_GIMU = 'https://www.jftc.go.jp/toriteki/toritekigaiyo/oyagimu.html';
const SRC_DECK = 'https://wwwtb.mlit.go.jp/chubu/jidosya/ouensaito/shiryou/20260309/20260309_05.pdf';
const SRC_SHINKOKU = 'https://www.jftc.go.jp/soudan/denshimadoguchi/';
const SRC_STAT = 'https://www.chusho.meti.go.jp/keiei/torihiki/2026/260610001_01.pdf';

// 点検の項目。side: o=発注する側の点検, u=受ける側の点検（取引先にされていないか）, b=両方
// [id, side, 区分, 発注側の問い, 受注側の問い, 該当する義務・禁止, 何が問題か, どうするか, 役に立つ当社のシステム(キー)]
const ITEMS = [
    ['meiji', 'b', '義務', '発注のたびに、発注書（紙かメール等の電子）で、給付の内容・代金の額・支払期日・受領の期日と場所などを示していますか？', '発注のたびに、発注書（紙か電子）で、内容・代金・支払期日・納期などを受け取っていますか？',
        '発注内容等の明示義務（第4条）', '電話や口頭だけの発注、「詳細は別途」のままの発注は義務違反になります。改正で、電子（メール・システム等）で示すのに受注者の承諾は要らなくなりました。', '発注書の項目（11項目）を決まった形で出す。電子で出すなら、出した記録が残る仕組みにする。', ['kweborder']],
    ['hozon', 'o', '義務', '取引の内容（給付・代金・受領日・支払日など）を書面か電子で記録し、2年間保存していますか？', '',
        '書類の作成・保存義務（第7条）', '記録が無いと、支払期日や減額の有無を後から説明できません。', '発注・受領・支払の記録を1か所に残し、2年は消さない。', ['kweborder', 'kbilling']],
    ['kijitsu', 'b', '義務', '支払期日を、受領した日から60日以内に決めて、その日までに払っていますか？', '代金は、納品（役務の提供）から60日以内に支払われていますか？',
        '支払期日を定める義務（第3条）・支払遅延の禁止', '受領後60日を過ぎると支払遅延です。過ぎた日数に年14.6%の遅延利息がかかります。違反のうち最も多いのが支払遅延です。', '締め日と支払日を見直し、受領日から60日以内に払う。請求と支払の期日を一覧で管理する。', ['kbilling']],
    ['tegata', 'b', '禁止', '手形で払うこと、または電子記録債権・ファクタリングなどで、支払期日までに満額の現金を受け取れない払い方をすることがありますか？', '手形で払われたり、支払期日までに満額の現金を受け取れない払い方（手数料を引かれる等）をされたりしていますか？',
        '支払遅延（手形払等の禁止）', '改正で手形払いは禁止になりました。電子記録債権・ファクタリングも、支払期日までに代金満額の現金を得にくいものは認められません。', '現金（振込）で、支払期日までに満額を払う形に切り替える。', ['kbilling']],
    ['kyogi', 'b', '禁止', '取引先から価格の見直し（協議）を求められたとき、応じて、必要な説明や情報を出していますか？（据え置きも「決定」に入ります）', '人件費や原材料が上がったとき、価格の協議を求めて、相手は応じましたか？',
        '協議に応じない一方的な代金決定の禁止（第5条第2項第4号）', '協議を拒む・無視する・先延ばしにする、説明や根拠を出さないまま代金を決める（据え置く）ことが禁止になりました。労務費・原材料・エネルギーの上昇、納期の短縮、数量の減少なども協議を求める理由になります。', '協議を求めた日・求めた内容・相手の回答を記録に残す。発注側は、協議の記録と説明した根拠を残す。', ['kdealdesk']],
    ['gengaku', 'b', '禁止', '発注した後で、代金を減らしたり差し引いたりすることがありますか？（協賛金・値引き・一方的な差し引きなど）', '発注の後で、代金を減らされたり差し引かれたりしたことがありますか？',
        '減額の禁止', '受注者に責任が無いのに、発注時に決めた代金を後から減らすことは禁止です。', '代金を変えるときは発注書を出し直し、理由と合意の記録を残す。', ['kweborder']],
    ['kaitataki', 'b', '禁止', '通常の対価より著しく低い代金を、一方的に決めることがありますか？', '同じような仕事の相場より、著しく低い代金を一方的に決められたことがありますか？',
        '買いたたきの禁止', '通常支払われる対価に比べて著しく低い代金を不当に定めることは禁止です。', '見積もりの根拠（数量・単価・コスト）を残して、協議して決める。', ['kdealdesk']],
    ['juryo', 'b', '禁止', '受注者に責任が無いのに、納品の受け取りを拒んだり、受け取った後で返品したりすることがありますか？', '責任が無いのに、納品を受け取ってもらえなかったり、返品されたりしたことがありますか？',
        '受領拒否・返品の禁止', '注文したものの受け取りを拒むこと、受け取った後に不当に返すことは禁止です。', '検査の基準と期日を発注書に書き、検査の結果を記録する。', ['kweborder']],
    ['henko', 'b', '禁止', '受注者に責任が無いのに、発注内容を変えたり、やり直しをさせたりして、その費用を負担させることがありますか？', '責任が無いのに、仕様の変更ややり直しを、費用を払ってもらえないままさせられたことがありますか？',
        '不当な給付内容の変更・やり直しの禁止', '受注者の利益を不当に害する変更・やり直しは禁止です。', '変更は発注書の出し直しで記録し、追加の費用を協議する。', ['kweborder', 'kdealdesk']],
    ['rieki', 'b', '禁止', '協賛金・無償の作業・金型の無償保管などを求めることがありますか？ 運送を頼む場合、荷積み・荷下ろし・荷待ちを無償でさせることがありますか？', '協賛金や無償の作業を求められたことがありますか？ 運送で、荷積み・荷下ろし・荷待ちを無償でさせられていますか？',
        '不当な経済上の利益提供要請の禁止', '自社のために金銭や役務を無償で提供させることは禁止です。運送の委託で、運送以外の作業（荷積み・荷下ろし・倉庫内作業）を無償でさせることもこれに当たります。', '運送以外の作業は、範囲と料金を発注書に書く。', ['kweborder']],
    ['kyosei', 'b', '禁止', '自社の指定する製品・サービスの購入や利用を、正当な理由なく求めることがありますか？', '取引先が指定する製品やサービスの購入・利用を強いられたことがありますか？',
        '購入・利用強制の禁止', '正当な理由が無いのに、自社の指定する物の購入や役務の利用を強制することは禁止です。', '購入や利用は任意であることを書面で示す。', []],
    ['soukihi', 'o', '禁止', '原材料などを有償で支給している場合、その代金を、それを使った製品の代金の支払より先に払わせることがありますか？', '',
        '有償支給原材料等の対価の早期決済の禁止', '有償で支給した原材料の対価を、それを使った給付の代金の支払期日より早く相殺・支払わせることは禁止です。', '支給の品名・数量・対価・決済期日を発注書に書き、代金の支払と同じ時期に精算する。', ['kbilling']],
    ['hofuku', 'b', '禁止', '受注者が公正取引委員会・中小企業庁・所管の省庁に知らせたことを理由に、取引を減らしたり止めたりしたことがありますか？', '',
        '報復措置の禁止', '申告したことを理由とする不利益な取扱いは禁止です。改正で、申告先に事業を所管する省庁も加わりました。', '社内に「申告を理由に取引を変えない」ことを周知する。', []],
];

const TOOLS = [
    'kweborder' => ['Kurage Web Order（受発注）', '88acc4be142bd444', 'https://proto.exbridge.jp/kweborder/', '発注の内容・数量・期日を、取引先とWEBで同じ画面で確かめ、記録を残す'],
    'kbilling'  => ['Kurage 請求書（kbilling）', '15abb025dc2ee4f6', 'https://proto.exbridge.jp/kbilling/', '請求書の発行と入金の確認。支払期日の一覧で遅れを見落とさない'],
    'kdealdesk' => ['Kurage Deal Desk（商談の記録）', '63000166082c7a26', 'https://proto.exbridge.jp/kdealdesk/', '取引先ごとに、価格の協議を求めた日・内容・回答を記録して残す'],
];

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function u(string $p): string { return BASE . $p; }

function page(string $title, string $desc, string $url, string $body, array $ld = [], array $crumbs = []): void {
    $ld[] = ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => NAME, 'url' => BASE . '/',
             'publisher' => ['@type' => 'Organization', 'name' => '株式会社エクスブリッジ', 'url' => 'https://exbridge.jp/']];
    if ($crumbs) {
        $items = [['@type' => 'ListItem', 'position' => 1, 'name' => NAME, 'item' => BASE . '/']];
        foreach ($crumbs as $i => [$n, $l]) { $items[] = ['@type' => 'ListItem', 'position' => $i + 2, 'name' => $n, 'item' => $l]; }
        $ld[] = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . h($title) . '</title><meta name="description" content="' . h($desc) . '"><link rel="canonical" href="' . h($url) . '">';
    echo '<meta property="og:type" content="website"><meta property="og:site_name" content="' . h(NAME) . '"><meta property="og:title" content="' . h($title) . '"><meta property="og:description" content="' . h($desc) . '"><meta property="og:url" content="' . h($url) . '"><meta property="og:image" content="' . SITE . '/images/ogp/ktoriteki.png"><meta property="og:locale" content="ja_JP"><meta name="twitter:card" content="summary_large_image"><meta name="color-scheme" content="light">';
    echo '<link rel="icon" href="https://exbridge.jp/images/logo-mark-128.png">';
    foreach ($ld as $j) { echo '<script type="application/ld+json">' . json_encode($j, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>'; }
    echo '<style>
:root{--navy:#1b2a41;--teal:#0f766e;--teal-l:#e6f4f2;--ink:#1f2933;--sub:#5b6676;--line:#dde3ea;--bg:#f6f8f8;--ok:#047857;--ok-l:#e7f6ef;--ng:#b42318;--ng-l:#fdecea;--amber:#9a6700;--amber-l:#fff6e0}
*{box-sizing:border-box}html{color-scheme:light}body{margin:0;background:#fff;color:var(--ink);font:15.5px/1.75 "Noto Sans JP","Hiragino Sans","Yu Gothic",system-ui,sans-serif}
a{color:var(--teal);overflow-wrap:anywhere}.wrap{max-width:980px;margin:0 auto;padding:0 16px;min-width:0}
header.top{border-bottom:1px solid var(--line);background:#fff}header.top .wrap{display:flex;align-items:center;gap:6px 16px;flex-wrap:wrap;min-height:58px;padding-top:6px;padding-bottom:6px}
.brand{display:flex;align-items:center;gap:10px;color:var(--navy);text-decoration:none;font-weight:800;font-size:16px;line-height:1.3}.brand img{width:34px;height:34px;object-fit:contain;flex:none}.brand small{display:block;color:var(--teal);font-size:11.5px;font-weight:700}
nav.menu{display:flex;flex-wrap:wrap;gap:2px 14px;font-size:14px;font-weight:700}nav.menu a{color:var(--sub);text-decoration:none}
.promo{margin-left:auto;display:flex;flex-wrap:wrap;gap:2px 12px;font-size:13px;font-weight:800}.promo a{color:#b45309;text-decoration:none}
main{padding:10px 0 40px}h1{font-size:clamp(22px,4vw,30px);line-height:1.4;color:var(--navy);margin:18px 0 8px;text-wrap:balance}
h2{font-size:19px;color:var(--navy);margin:30px 0 10px;border-left:5px solid var(--teal);padding-left:10px}h3{font-size:16px;color:var(--navy);margin:18px 0 6px}
.lead{color:var(--sub);margin:0 0 14px}.panel{background:var(--bg);border:1px solid var(--line);border-radius:12px;padding:16px;margin:12px 0;min-width:0}
.big{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,200px),1fr));gap:10px;margin:12px 0}.big div{background:#fff;border:1px solid var(--line);border-radius:12px;padding:12px 14px;min-width:0}
.big .k{display:block;font-size:12.5px;color:var(--sub);font-weight:700}.big .v{display:block;font-size:22px;font-weight:800;color:var(--navy);line-height:1.35}.big .s{display:block;font-size:12.5px;color:var(--sub)}
.box-ok{background:var(--ok-l);border:1px solid #b7e4cf;border-radius:10px;padding:10px 14px;margin:10px 0}.box-ng{background:var(--ng-l);border:1px solid #f3c2bd;border-radius:10px;padding:10px 14px;margin:10px 0}
.note{background:var(--amber-l);border-left:4px solid var(--amber);border-radius:8px;padding:10px 12px;font-size:14px;margin:10px 0}.src{font-size:12.5px;color:var(--sub)}
label{font-weight:700;font-size:14px;display:block;margin:10px 0 4px}select,input{font:inherit;font-size:16px;padding:8px 10px;border:2px solid var(--line);border-radius:10px;max-width:100%;background:#fff}
.row{display:flex;flex-wrap:wrap;gap:10px 18px}.row>div{min-width:0}.seg{display:inline-flex;border:2px solid var(--teal);border-radius:10px;overflow:hidden}.seg button{font:inherit;font-weight:700;border:0;background:#fff;color:var(--teal);padding:8px 14px;cursor:pointer}.seg button.on{background:var(--teal);color:#fff}
.btn{display:inline-block;background:var(--teal);color:#fff;border:0;border-radius:10px;padding:10px 18px;font-weight:700;text-decoration:none;cursor:pointer;font-size:15px}.btn.sub{background:#fff;color:var(--teal);border:2px solid var(--teal)}
.q{background:#fff;border:1px solid var(--line);border-radius:12px;padding:12px 14px;margin:8px 0}.q p{margin:0 0 8px}.q .tag{display:inline-block;font-size:12px;font-weight:800;border-radius:999px;padding:0 9px;margin-right:6px;background:var(--teal-l);color:var(--teal)}.q .tag.kin{background:var(--ng-l);color:var(--ng)}
.ans{display:flex;flex-wrap:wrap;gap:6px 14px;font-size:14.5px}.ans label{display:inline-flex;align-items:center;gap:5px;font-weight:600;margin:0}
.res{border-left:5px solid var(--ng);background:#fff;border-radius:10px;padding:10px 14px;margin:10px 0;border-top:1px solid var(--line);border-right:1px solid var(--line);border-bottom:1px solid var(--line)}.res.unk{border-left-color:var(--amber)}.res b{color:var(--navy)}.res p{margin:4px 0;font-size:14.5px}
.cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,260px),1fr));gap:10px}.card{background:#fff;border:1px solid var(--line);border-radius:12px;padding:12px 14px;min-width:0}.card b{color:var(--navy)}.card p{margin:4px 0 8px;font-size:14px;color:var(--sub)}.card .a{display:flex;gap:10px;flex-wrap:wrap;font-size:14px;font-weight:700}
.tbl{overflow-x:auto}table{border-collapse:collapse;width:100%;font-size:14px}th,td{border-bottom:1px solid var(--line);padding:7px 8px;text-align:left;vertical-align:top}th{color:var(--sub)}
details{border-bottom:1px solid var(--line);padding:8px 0}summary{cursor:pointer;font-weight:700}
footer{margin-top:40px;border-top:1px solid var(--line);padding:18px 0;font-size:13px;color:var(--sub)}footer p{margin:6px 0}
.xshare{display:inline-flex;align-items:center;gap:8px;background:#0f1419;color:#fff;text-decoration:none;font-weight:800;font-size:14px;border-radius:999px;padding:8px 16px}
</style></head><body>';
    echo '<header class="top"><div class="wrap"><a class="brand" href="' . u('/') . '"><img src="https://exbridge.jp/images/logo-mark-128.png" width="34" height="34" alt="株式会社エクスブリッジ"><span>' . h(NAME) . '<small>取適法（旧 下請法）の対象判定と自己点検</small></span></a>';
    echo '<nav class="menu"><a href="' . u('/') . '">点検する</a><a href="' . u('/gimu/') . '">義務と禁止行為</a><a href="' . u('/about') . '">出典</a></nav>';
    echo '<!--kurage-only--><span class="promo"><a href="https://exbridge.jp/ai-it-komon.html?ref=ktoriteki-head-komon" target="_blank" rel="noopener">AI-IT顧問</a><a href="https://kurage.exbridge.jp/vibe-customize.html?ref=ktoriteki-head-vibe" target="_blank" rel="noopener">バイブカスタマイズ</a><a href="https://kurage.exbridge.jp/reseller.html?ref=ktoriteki-head-reseller" target="_blank" rel="noopener">販売代理店募集</a></span><!--/kurage-only-->';
    echo '</div></header><main class="wrap">' . $body . '</main>';
    echo '<footer><div class="wrap">';
    echo '<p>出典：公正取引委員会「<a href="' . SRC_GIMU . '" rel="noopener">委託事業者の義務</a>」、公正取引委員会 中部事務所「<a href="' . SRC_DECK . '" rel="noopener">中小受託取引適正化法（取適法）について</a>」（令和8年3月9日）。' . (int)substr(CHECKED, 5, 2) . '月' . (int)substr(CHECKED, 8, 2) . '日確認。</p>';
    echo '<p>このページは法律の助言ではありません。対象になるか・違反に当たるかの個別の判断は、公正取引委員会・中小企業庁の相談窓口か弁護士にご相談ください。入力した答えはブラウザの中だけで判定し、送信・保存しません。</p>';
    echo '<p><a href="https://exbridge.jp/?ref=ktoriteki">株式会社エクスブリッジ</a>（名古屋・業務システム開発）・<a href="' . SITE . '/kchingin.php/?ref=ktoriteki">Kurage 最低賃金・賃上げ助成金チェック</a>・<a href="https://exbridge.jp/ai-agent-automation.html?ref=ktoriteki-foot">AIエージェントで業務を自動化する</a></p>';
    echo '</div></footer>';
    echo '<img src="' . SITE . '/simpletrack.php?t=img&url=' . rawurlencode($url) . '&ref=' . rawurlencode((string)($_GET['ref'] ?? '')) . '" width="1" height="1" alt="" aria-hidden="true" style="position:absolute;left:-9999px">';
    if (($_SERVER['HTTP_HOST'] ?? '') === 'kurage.exbridge.jp') echo '<script src="https://kurage.exbridge.jp/partner-bar.js" defer></script>';
    echo '</body></html>';
}

function faq(array $qa): array {
    $html = ''; $ld = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => []];
    foreach ($qa as [$q, $a]) {
        $html .= '<details><summary>' . h($q) . '</summary><p>' . h($a) . '</p></details>';
        $ld['mainEntity'][] = ['@type' => 'Question', 'name' => $q, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a]];
    }
    return [$html, $ld];
}

function checker(): string {
    $data = json_encode(['items' => array_map(fn($r) => ['id' => $r[0], 'side' => $r[1], 'kind' => $r[2], 'qo' => $r[3], 'qu' => $r[4], 'rule' => $r[5], 'why' => $r[6], 'todo' => $r[7], 'tools' => $r[8]], ITEMS),
        'tools' => TOOLS, 'shinkoku' => SRC_SHINKOKU], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $types = ['seizo' => '製造委託（部品・製品・金型などを作らせる）', 'shuri' => '修理委託（請け負った修理や自社の機械の修理を任せる）', 'prog' => '情報成果物作成委託：プログラム（ソフトウェア開発など）',
              'joho' => '情報成果物作成委託：プログラム以外（デザイン・映像・文章・設計図など）', 'eki_a' => '役務提供委託：運送・倉庫保管・情報処理',
              'eki_b' => '役務提供委託：それ以外（清掃・保守・コールセンター・ビル管理など）', 'unso' => '特定運送委託（自社が売った・作った物を取引先へ運ぶ運送を任せる）'];
    $o = ''; foreach ($types as $k => $v) { $o .= '<option value="' . $k . '">' . h($v) . '</option>'; }
    $caps = ['0' => '個人（法人でない）', '5' => '1千万円以下', '30' => '1千万円超〜5千万円以下', '100' => '5千万円超〜3億円以下', '500' => '3億円超'];
    $co = ''; foreach ($caps as $k => $v) { $co .= '<option value="' . $k . '">' . h($v) . '</option>'; }
    $emp = ['50' => '100人以下', '200' => '101〜300人', '500' => '301人以上'];
    $eo = ''; foreach ($emp as $k => $v) { $eo .= '<option value="' . $k . '">' . h($v) . '</option>'; }
    return '<div class="panel" id="chk">'
        . '<p style="margin:0 0 6px"><b>あなたの会社は</b></p><div class="seg" role="group" aria-label="立場"><button type="button" id="sideO" class="on">発注する側</button><button type="button" id="sideU">受ける側</button></div>'
        . '<h3>1. この取引は取適法の対象か</h3>'
        . '<label for="type">取引の内容</label><select id="type">' . $o . '</select>'
        . '<p class="src" id="ekiNote" hidden>役務提供委託は、自社が<b>お客さまから請け負った</b>役務（運送・清掃・保守など）の全部か一部を他社に任せる場合が対象です。自社のために使う清掃・警備などを頼む場合は対象外です。</p>'
        . '<div class="row"><div><label for="oc">発注する会社の資本金</label><select id="oc">' . str_replace('value="500"', 'value="500" selected', $co) . '</select></div><div><label for="oe">発注する会社の常時使用する従業員</label><select id="oe">' . $eo . '</select></div></div>'
        . '<div class="row"><div><label for="uc">受ける会社の資本金</label><select id="uc">' . $co . '</select></div><div><label for="ue">受ける会社の常時使用する従業員</label><select id="ue">' . $eo . '</select></div></div>'
        . '<div id="appl" aria-live="polite"></div>'
        . '<h3>2. 気をつけるところの点検</h3><p class="src" id="qlead"></p><div id="qs"></div>'
        . '<p><button class="btn" type="button" id="go">点検の結果を見る</button></p><div id="out" aria-live="polite"></div></div>'
        . <<<JS
<script>
(function(){
var D={$data},side='o';
function esc(s){return String(s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
function v(id){return document.getElementById(id).value}
function appl(){
  var t=v('type'),oc=+v('oc'),uc=+v('uc'),oe=+v('oe'),ue=+v('ue'),A=(t==='seizo'||t==='shuri'||t==='prog'||t==='eki_a'||t==='unso'),cap=false,emp=false,why='';
  document.getElementById('ekiNote').hidden=!(t==='eki_a'||t==='eki_b');
  if(A){cap=(oc>300&&uc<=300)||(oc>10&&oc<=300&&uc<=10);emp=oe>300&&ue<=300;}
  else{cap=(oc>50&&uc<=50)||(oc>10&&oc<=50&&uc<=10);emp=oe>100&&ue<=100;}
  var e=document.getElementById('appl');
  if(cap||emp){
    why=cap?'資本金の基準':'従業員の基準（改正で新しく加わった基準。資本金の基準に当たらない場合に使う）';
    e.innerHTML='<p class="box-ng"><b>取適法の対象になる取引です</b>（'+why+'で当たります）。発注する会社は「委託事業者」、受ける会社は「中小受託事業者」です。下の点検に進んでください。</p>';
  }else{
    e.innerHTML='<p class="box-ok"><b>規模の要件では、取適法の対象になりません。</b> ただし取引ごとに判断するので、別の取引先・別の取引の内容でも確かめてください。資本金で当たらなくても、従業員数（'+(A?'300':'100')+'人）で当たることがあります。</p>';
  }
  return cap||emp;
}
function qs(){
  var h='';D.items.forEach(function(it){
    if(side==='u'&&!it.qu)return; if(side==='o'&&it.side==='u')return;
    var q=side==='o'?it.qo:it.qu;
    h+='<div class="q"><p><span class="tag'+(it.kind==='禁止'?' kin':'')+'">'+esc(it.kind)+'</span>'+esc(q)+'</p><div class="ans">'
      +'<label><input type="radio" name="'+it.id+'" value="y"> はい</label><label><input type="radio" name="'+it.id+'" value="n"> いいえ</label><label><input type="radio" name="'+it.id+'" value="u" checked> 分からない</label></div></div>';
  });
  document.getElementById('qs').innerHTML=h;
  document.getElementById('qlead').textContent=side==='o'?'発注する側として、自社の取引を点検します。':'受ける側として、取引先からこういう扱いを受けていないかを点検します。';
}
// 「はい」が良い答えの問い（義務を守っているか・相手が守っているか）と、「はい」が悪い答えの問い（禁止行為をしている・されている）を分ける
var GOOD_YES={meiji:1,hozon:1,kijitsu:1,kyogi:1};
function run(){
  var on=appl(),bad=[],unk=[];
  D.items.forEach(function(it){
    if(side==='u'&&!it.qu)return; if(side==='o'&&it.side==='u')return;
    var r=document.querySelector('input[name="'+it.id+'"]:checked');r=r?r.value:'u';
    var goodYes=!!GOOD_YES[it.id];
    var isBad=r==='u'?null:(goodYes?r==='n':r==='y');
    if(isBad===true)bad.push(it);else if(isBad===null)unk.push(it);
  });
  var h='<div class="big"><div><span class="k">気をつけるところ</span><span class="v">'+bad.length+'件</span><span class="s">答えから問題になりうる項目</span></div><div><span class="k">分からない</span><span class="v">'+unk.length+'件</span><span class="s">確かめたほうがよい項目</span></div></div>';
  if(!on)h+='<p class="note">規模の要件では対象外と出ています。それでも、同じ行為は独占禁止法の「優越的地位の濫用」に当たることがあります。</p>';
  function card(it,cls){
    var t=(it.tools||[]).map(function(k){var x=D.tools[k];return '<a href="https://kappstore.exbridge.jp/app.php?id='+x[1]+'&amp;ref=ktoriteki-'+it.id+'" target="_blank" rel="noopener">'+esc(x[0])+'</a>'}).join('・');
    return '<div class="res'+cls+'"><b>'+esc(it.rule)+'</b><p>'+esc(it.why)+'</p><p><b>どうするか：</b>'+esc(side==='o'?it.todo:(it.id==='kyogi'?'協議を求めた日・内容・相手の回答を記録して残す。応じてもらえないときは、公正取引委員会・中小企業庁の窓口に相談できます（申告を理由に取引を減らすことは「報復措置」として禁止）。':'取引の記録（発注書・納品・入金）を残し、公正取引委員会・中小企業庁の窓口に相談できます。'))+'</p>'+(t?'<p class="src">記録を残す仕組み：'+t+'</p>':'')+'</div>';
  }
  if(bad.length){h+='<h3>気をつけるところ</h3>'+bad.map(function(it){return card(it,'')}).join('')}
  if(unk.length){h+='<h3>分からない項目</h3>'+unk.map(function(it){return card(it,' unk')}).join('')}
  if(!bad.length&&!unk.length){h+='<p class="box-ok">答えの範囲では、気をつけるところは見つかりませんでした。取引の記録を残し続けることが、いちばんの備えです。</p>'}
  if(side==='u')h+='<p class="src">相談・申告：<a href="'+D.shinkoku+'" target="_blank" rel="noopener">公正取引委員会 オンラインの申告窓口</a></p>';
  h+='<h3>記録を残す仕組みを、自社のサーバーに</h3><div class="cards">'+Object.keys(D.tools).map(function(k){var x=D.tools[k];return '<div class="card"><b>'+esc(x[0])+'</b><p>'+esc(x[3])+'</p><div class="a"><a href="https://kappstore.exbridge.jp/app.php?id='+x[1]+'&amp;ref=ktoriteki-calc" target="_blank" rel="noopener">商品ページ</a><a href="'+x[2]+'?ref=ktoriteki-calc" target="_blank" rel="noopener">デモ</a></div></div>'}).join('')
    +'</div><p style="margin-top:12px"><a class="btn sub" href="https://exbridge.jp/ai-it-komon.html?ref=ktoriteki-calc" target="_blank" rel="noopener">発注書と支払の流れを一緒に見直す（AI-IT顧問）</a></p>';
  document.getElementById('out').innerHTML=h;
}
function setSide(s){side=s;document.getElementById('sideO').className=s==='o'?'on':'';document.getElementById('sideU').className=s==='u'?'on':'';qs();document.getElementById('out').innerHTML='';}
document.getElementById('sideO').onclick=function(){setSide('o')};document.getElementById('sideU').onclick=function(){setSide('u')};
['type','oc','oe','uc','ue'].forEach(function(id){document.getElementById(id).addEventListener('change',appl)});
document.getElementById('go').onclick=run;
qs();appl();
})();
</script>
JS;
}

// ---------------- ルーティング ----------------
$path = $_SERVER['PATH_INFO'] ?? '';
if ($path === '' && !str_ends_with((string)($_SERVER['REQUEST_URI'] ?? ''), '/') && !str_contains((string)($_SERVER['REQUEST_URI'] ?? ''), '?')) {
    header('Location: ' . BASE . '/', true, 302); exit;
}
if ($path === '/robots.txt') { header('Content-Type: text/plain; charset=UTF-8'); echo "User-agent: *\nAllow: /\nSitemap: " . u('/sitemap.xml') . "\n"; exit; }
if ($path === '/sitemap.xml') {
    header('Content-Type: application/xml; charset=UTF-8');
    echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach (['/', '/gimu/', '/about'] as $p) { echo '<url><loc>' . h(u($p)) . '</loc><lastmod>' . CHECKED . '</lastmod></url>'; }
    echo '</urlset>'; exit;
}
if ($path === '/llms.txt') {
    header('Content-Type: text/plain; charset=UTF-8');
    $l = ['# ' . NAME, '', '> 2026年1月1日施行の取適法（中小受託取引適正化法・旧 下請法）について、その取引が対象になるか（取引の内容＋資本金・従業員の規模要件）と、4つの義務・11の禁止行為のどこに気をつけるかを、発注する側・受ける側の質問に答えて確かめるサイト。判定はブラウザの中だけで行う。株式会社エクスブリッジ製。法律の助言ではない。', '',
        '## 改正の要点（下請法→取適法）', '- 特定運送委託（発荷主が運送事業者に物品の運送を委託）を対象に追加', '- 従業員基準を追加：製造委託等は従業員300人、役務提供委託等は100人。資本金基準に当たらない場合に使う',
        '- 協議に応じない一方的な代金決定の禁止（第5条第2項第4号）。据え置きも「決定」に含む', '- 手形払の禁止。電子記録債権・ファクタリングも支払期日までに満額の現金を得にくいものは不可', '- 用語：親事業者→委託事業者、下請事業者→中小受託事業者、下請代金→製造委託等代金',
        '', '## 委託事業者の4つの義務', '- 発注内容等の明示（第4条。書面か電磁的方法。電磁的方法に受注者の承諾は不要に）', '- 書類の作成・2年間保存（第7条）', '- 支払期日を受領後60日以内に定める（第3条）', '- 遅延利息（年14.6%）の支払',
        '', '## 11の禁止行為', '- 受領拒否・支払遅延（手形払等の禁止）・減額・返品・買いたたき・購入利用強制・報復措置・有償支給原材料等の対価の早期決済・割引困難な手形の交付・不当な経済上の利益提供要請・不当な給付内容の変更やり直し・協議に応じない一方的な代金決定',
        '', '## ページ', '- 点検: ' . u('/'), '- 義務と禁止行為: ' . u('/gimu/'), '- 出典: ' . u('/about')];
    echo implode("\n", $l) . "\n"; exit;
}
$seg = array_values(array_filter(explode('/', $path), fn($x) => $x !== ''));

if (($seg[0] ?? '') === 'gimu') {
    $url = u('/gimu/');
    $rows = ''; foreach (ITEMS as $r) { $rows .= '<tr><td>' . h($r[2]) . '</td><td><b>' . h($r[5]) . '</b><br><span class="src">' . h($r[6]) . '</span></td></tr>'; }
    $body = '<h1>取適法の4つの義務と11の禁止行為（2026年1月施行）</h1>'
        . '<p class="lead">取適法（中小受託取引適正化法）は、下請法を改正して2026年1月1日に施行されました。正式名称は「製造委託等に係る中小受託事業者に対する代金の支払の遅延等の防止に関する法律」です。発注する側（委託事業者）に4つの義務と11の禁止行為を定めています。</p>'
        . '<h2>下請法からの主な改正点</h2><ol><li><b>特定運送委託を追加</b>：発荷主が運送事業者に物品の運送を委託する取引も対象に。運送以外の荷積み・荷下ろし・荷待ちを無償でさせることは「不当な経済上の利益提供要請」</li><li><b>従業員基準を追加</b>：資本金で当たらなくても、従業員300人（役務提供委託等は100人）で当たることがある</li><li><b>協議に応じない一方的な代金決定の禁止</b>：協議を拒む・無視する・先延ばしにする、説明をしないまま代金を決めること（据え置きを含む）</li><li><b>手形払の禁止</b>：電子記録債権・ファクタリングも、支払期日までに満額の現金を得にくいものは不可</li><li><b>面的執行の強化</b>：事業を所管する省庁にも指導・助言の権限。報復措置の禁止の申告先にも加わった</li></ol>'
        . '<h2>規模の要件</h2><div class="tbl"><table><tr><th>取引の内容</th><th>発注する側</th><th>受ける側</th></tr>'
        . '<tr><td rowspan="3">製造委託・修理委託・情報成果物作成委託（プログラム）・役務提供委託（運送・倉庫保管・情報処理）・特定運送委託</td><td>資本金3億円超</td><td>資本金3億円以下（個人を含む）</td></tr><tr><td>資本金1千万円超3億円以下</td><td>資本金1千万円以下（個人を含む）</td></tr><tr><td>常時使用する従業員300人超</td><td>常時使用する従業員300人以下（個人を含む）</td></tr>'
        . '<tr><td rowspan="3">情報成果物作成委託（プログラム以外）・役務提供委託（運送・倉庫保管・情報処理以外）</td><td>資本金5千万円超</td><td>資本金5千万円以下（個人を含む）</td></tr><tr><td>資本金1千万円超5千万円以下</td><td>資本金1千万円以下（個人を含む）</td></tr><tr><td>常時使用する従業員100人超</td><td>常時使用する従業員100人以下（個人を含む）</td></tr></table></div>'
        . '<p class="src">取引ごとに判断します。従業員の基準は、資本金の基準に当たらない場合に使います（両方に当たる場合は資本金の基準）。</p>'
        . '<h2>義務と禁止行為の一覧</h2><div class="tbl"><table><tr><th>区分</th><th>内容</th></tr>' . $rows . '</table></div>'
        . '<p class="src">割引困難な手形の交付の禁止もあります（手形払そのものが禁止になりました）。遅延利息は、受領後60日を経過した日から支払う日までの日数に、年14.6%です。</p>'
        . '<p><a class="btn" href="' . u('/') . '">自社の取引を点検する</a></p>';
    [$fh, $fl] = faq([
        ['取適法と下請法の違いは？', '取適法は下請法を改正したもので、2026年1月1日に施行されました。特定運送委託の追加、従業員基準の追加、協議に応じない一方的な代金決定の禁止、手形払の禁止などが加わり、親事業者は委託事業者、下請事業者は中小受託事業者と呼び方が変わりました。'],
        ['発注書はメールで出してもよいですか？', 'はい。書面か電磁的方法で発注内容を明示します。下請法では電磁的方法に受注者の承諾が必要でしたが、取適法では不要になりました。'],
        ['支払期日は何日以内ですか？', '給付を受領した日（役務提供委託は役務の提供をした日）から60日以内で、できる限り短い期間に定めます。過ぎた場合は年14.6%の遅延利息がかかります。'],
    ]);
    $body .= '<h2>よくある質問</h2>' . $fh;
    page('取適法の義務と禁止行為11項目｜下請法からの改正点', '取適法（中小受託取引適正化法）の4つの義務と11の禁止行為、下請法からの改正点（特定運送委託・従業員基準・協議に応じない一方的な代金決定・手形払の禁止）と規模の要件を、公正取引委員会の資料から一覧にしました。', $url, $body, [$fl], [['義務と禁止行為', $url]]);
    exit;
}

if (($seg[0] ?? '') === 'about') {
    $url = u('/about');
    $body = '<h1>出典と判定のしかた</h1><h2>出典</h2><ul><li>公正取引委員会「<a href="' . SRC_GIMU . '" rel="noopener">委託事業者の義務</a>」</li><li>公正取引委員会 中部事務所「<a href="' . SRC_DECK . '" rel="noopener">中小受託取引適正化法（取適法）について</a>」（令和8年3月9日）</li><li>違反の内訳（支払遅延が最多）：<a href="' . SRC_STAT . '" rel="noopener">中小企業庁の資料</a></li></ul>'
        . '<h2>判定のしかた</h2><p>対象かどうかは、取引の内容と、発注する会社・受ける会社の資本金・常時使用する従業員の区分から、公正取引委員会の資料の表どおりに判定します。点検は、答えが義務を守っていない・禁止行為に当たりうる項目を「気をつけるところ」、「分からない」と答えた項目を「確かめたほうがよい項目」として並べます。</p>'
        . '<h2>このサイトがしないこと</h2><p>法律の助言、違反かどうかの最終的な判断はしません。個別の判断は、公正取引委員会・中小企業庁の相談窓口か弁護士へ。入力した答えはブラウザの中だけで判定し、送信・保存しません。</p>';
    page('出典と判定のしかた｜' . NAME, '取適法の対象判定と自己点検に使った公正取引委員会の資料（委託事業者の義務・中部事務所の説明資料）と、判定のしかた。入力した答えは送信も保存もせず、ブラウザの中だけで判定します。法律の助言ではありません。', $url, $body, [], [['出典', $url]]);
    exit;
}

if ($seg) { http_response_code(404); page('見つかりません｜' . NAME, '', BASE . '/', '<h1>見つかりません</h1><p><a class="btn" href="' . u('/') . '">入口へ戻る</a></p>'); exit; }

$body = '<h1>取適法の対象か、どこに気をつけるかを点検する</h1>'
    . '<p class="lead">取適法（中小受託取引適正化法）は、下請法を改正して2026年1月1日に施行されました。手形払の禁止、価格の協議に応じない一方的な代金決定の禁止、従業員数の基準、運送委託の追加などが加わっています。発注する側か受ける側かを選び、取引の内容と会社の規模、いくつかの質問に答えると、対象になるかと気をつけるところが出ます。</p>'
    . '<div class="big"><div><span class="k">施行</span><span class="v">2026年1月1日</span><span class="s">下請法から改正</span></div><div><span class="k">委託事業者の義務と禁止</span><span class="v">4つ・11項目</span><span class="s">公正取引委員会の資料から</span></div><div><span class="k">入力した答え</span><span class="v">送信しません</span><span class="s">ブラウザの中だけで判定</span></div></div>'
    . checker();
$q = http_build_query(['text' => '取適法（旧 下請法）の対象か、どこに気をつけるかを、質問に答えるだけで点検できます。発注する側・受ける側の両方', 'url' => BASE . '/?ref=x-share-ktoriteki', 'hashtags' => '取適法']);
$body .= '<p><a class="xshare" href="https://x.com/intent/post?' . h($q) . '" target="_blank" rel="noopener">𝕏 このページをXで共有</a></p>';
$body .= '<h2>改正の要点</h2><ul><li><b>手形払の禁止</b>：受領後60日以内の支払期日までに、満額の現金で払う</li><li><b>協議に応じない一方的な代金決定の禁止</b>：人件費・原材料の上昇などで協議を求められたら応じ、説明する（据え置きも含む）</li><li><b>従業員基準</b>：資本金が小さくても、従業員300人（役務提供委託等は100人）超の会社は対象になりうる</li><li><b>特定運送委託</b>：自社の荷物の運送を頼む取引も対象。荷積み・荷下ろし・荷待ちを無償でさせない</li></ul><p><a href="' . u('/gimu/') . '">4つの義務と11の禁止行為の一覧 →</a></p>';
[$fh, $fl] = faq([
    ['取適法とは何ですか？', '中小受託取引適正化法の略称で、下請法を改正して2026年1月1日に施行された法律です。発注する側（委託事業者）に、発注内容の明示・書類の保存・60日以内の支払期日・遅延利息の4つの義務と、支払遅延や減額など11の禁止行為を定めています。'],
    ['うちの会社は取適法の対象ですか？', '取引ごとに、取引の内容と、発注する会社・受ける会社の資本金（または常時使用する従業員の数）で決まります。このページの「この取引は取適法の対象か」で確かめられます。'],
    ['価格の協議を断ると違反になりますか？', '受注者から協議を求められたのに応じない、必要な説明や情報を出さないまま一方的に代金を決める（据え置きを含む）ことは、取適法で禁止されました。'],
    ['手形での支払はもうできませんか？', '取適法の対象取引では手形払が禁止されました。電子記録債権やファクタリングも、支払期日までに代金満額の現金を得ることが難しいものは認められません。'],
]);
$body .= '<h2>よくある質問</h2>' . $fh;
page('取適法の対象か自己点検｜下請法改正の義務と禁止行為', '取適法（中小受託取引適正化法・旧 下請法）の対象になるかを、取引の内容と資本金・従業員数から判定し、4つの義務と11の禁止行為のどこに気をつけるかを発注する側・受ける側の質問で点検します。入力は送信しません。', BASE . '/', $body, [$fl]);
