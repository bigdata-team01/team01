<?php
// 가격 자료 검색(SELECT) 페이지 (작성: 유채은)
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/auth/session.php';
require_once __DIR__ . '/crud_layout.php';

const PER_PAGE = 20;
const GRADES = ['상품', '중품', 'S과', 'M과', 'L과'];

// 정렬 기준은 SQL에 바인딩할 수 없으므로 허용 목록에서만 고릅니다.
const SORTS = [
    'ym_desc'    => ['최신 월순', 'p.ym DESC, f.fruit_id, v.variety_id, m.market_id, p.grade, p.price_id'],
    'ym_asc'     => ['오래된 월순', 'p.ym ASC, f.fruit_id, v.variety_id, m.market_id, p.grade, p.price_id'],
    'price_desc' => ['평균가 높은순', 'p.avg_price DESC, p.ym DESC, p.price_id'],
    'price_asc'  => ['평균가 낮은순', 'p.avg_price ASC, p.ym DESC, p.price_id'],
];

// ----- 검색 조건 읽기 -----
function int_param(string $name): ?int
{
    $value = $_GET[$name] ?? '';

    return (is_string($value) && ctype_digit($value)) ? (int) $value : null;
}

function month_param(string $name): ?string
{
    $value = $_GET[$name] ?? '';

    return (is_string($value) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value)) ? $value : null;
}

$filters = [
    'fruit_id'   => int_param('fruit_id'),
    'variety_id' => int_param('variety_id'),
    'market_id'  => int_param('market_id'),
    'grade'      => in_array($_GET['grade'] ?? '', GRADES, true) ? $_GET['grade'] : null,
    'from'       => month_param('from'),
    'to'         => month_param('to'),
    'min_price'  => int_param('min_price'),
    'max_price'  => int_param('max_price'),
];
$sort = array_key_exists($_GET['sort'] ?? '', SORTS) ? $_GET['sort'] : 'ym_desc';
$page = max(1, int_param('page') ?? 1);

// ----- WHERE 절 만들기 (값은 모두 바인딩) -----
$where = [];
$params = [];

if ($filters['fruit_id'] !== null) {
    $where[] = 'f.fruit_id = ?';
    $params[] = [$filters['fruit_id'], PDO::PARAM_INT];
}
if ($filters['variety_id'] !== null) {
    $where[] = 'v.variety_id = ?';
    $params[] = [$filters['variety_id'], PDO::PARAM_INT];
}
if ($filters['market_id'] !== null) {
    $where[] = 'm.market_id = ?';
    $params[] = [$filters['market_id'], PDO::PARAM_INT];
}
if ($filters['grade'] !== null) {
    $where[] = 'p.grade = ?';
    $params[] = [$filters['grade'], PDO::PARAM_STR];
}
if ($filters['from'] !== null) {
    $where[] = 'p.ym >= ?';
    $params[] = [$filters['from'] . '-01', PDO::PARAM_STR];
}
if ($filters['to'] !== null) {
    $where[] = 'p.ym <= ?';
    $params[] = [$filters['to'] . '-01', PDO::PARAM_STR];
}
if ($filters['min_price'] !== null) {
    $where[] = 'p.avg_price >= ?';
    $params[] = [$filters['min_price'], PDO::PARAM_INT];
}
if ($filters['max_price'] !== null) {
    $where[] = 'p.avg_price <= ?';
    $params[] = [$filters['max_price'], PDO::PARAM_INT];
}

$fromSql = '
    FROM PRICE_MONTHLY p
    JOIN FRUIT_VARIETY v ON v.variety_id = p.variety_id
    JOIN FRUIT f ON f.fruit_id = v.fruit_id
    JOIN MARKET_REGION m ON m.market_id = p.market_id';
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

function bind_all(PDOStatement $statement, array $params): void
{
    foreach ($params as $index => [$value, $type]) {
        $statement->bindValue($index + 1, $value, $type);
    }
}

$rows = [];
$total = 0;
$error = '';

try {
    // 검색 화면의 선택지
    $fruits = db()->query('SELECT fruit_id, fruit_name FROM FRUIT ORDER BY fruit_id')->fetchAll();
    $varieties = db()->query(
        'SELECT variety_id, fruit_id, variety_name FROM FRUIT_VARIETY ORDER BY fruit_id, variety_id'
    )->fetchAll();
    $markets = db()->query('SELECT market_id, market_name FROM MARKET_REGION ORDER BY market_id')->fetchAll();

    // 전체 건수
    $countStatement = db()->prepare('SELECT COUNT(*)' . $fromSql . $whereSql);
    bind_all($countStatement, $params);
    $countStatement->execute();
    $total = (int) $countStatement->fetchColumn();

    $totalPages = max(1, (int) ceil($total / PER_PAGE));
    $page = min($page, $totalPages);

    // 현재 페이지 행
    $listStatement = db()->prepare(
        'SELECT p.price_id, p.ym, p.grade, p.avg_price, p.min_price, p.max_price,
                f.fruit_name, v.variety_name, v.unit, m.market_name'
        . $fromSql . $whereSql
        . ' ORDER BY ' . SORTS[$sort][1]
        . ' LIMIT ? OFFSET ?'
    );
    $limitParams = array_merge($params, [[PER_PAGE, PDO::PARAM_INT], [($page - 1) * PER_PAGE, PDO::PARAM_INT]]);
    bind_all($listStatement, $limitParams);
    $listStatement->execute();
    $rows = $listStatement->fetchAll();
} catch (PDOException $exception) {
    $fruits = $varieties = $markets = [];
    $totalPages = 1;
    $error = '자료를 불러오지 못했습니다. DB 연결과 테이블을 확인하세요.';
}

// 현재 검색 조건을 유지한 주소 만들기
function list_url(array $filters, string $sort, int $page): string
{
    $query = array_filter($filters, fn ($value) => $value !== null);
    if ($sort !== 'ym_desc') {
        $query['sort'] = $sort;
    }
    if ($page > 1) {
        $query['page'] = $page;
    }

    return 'list.php' . ($query ? '?' . http_build_query($query) : '');
}

$currentUrl = list_url($filters, $sort, $page);
$loggedIn = is_logged_in();
$flash = take_flash();

crud_page_start('Browse Records');
?>
        <h1>가격 자료 검색</h1>
        <p class="subtitle">품목·품종·도시·등급·기간으로 월별 소매가격을 찾아보세요.</p>

        <?php if ($flash !== null): ?>
            <p class="message <?= e($flash['type']) ?>"><?= e($flash['text']) ?></p>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <p class="message error"><?= e($error) ?></p>
        <?php endif; ?>

        <form class="card" method="get" action="list.php">
            <div class="filters">
                <div class="field">
                    <label for="fruit_id">품목</label>
                    <select id="fruit_id" name="fruit_id">
                        <option value="">전체</option>
                        <?php foreach ($fruits as $fruit): ?>
                            <option value="<?= e($fruit['fruit_id']) ?>"
                                <?= $filters['fruit_id'] === (int) $fruit['fruit_id'] ? 'selected' : '' ?>>
                                <?= e($fruit['fruit_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="variety_id">품종</label>
                    <select id="variety_id" name="variety_id">
                        <option value="">전체</option>
                        <?php foreach ($varieties as $variety): ?>
                            <option value="<?= e($variety['variety_id']) ?>" data-fruit="<?= e($variety['fruit_id']) ?>"
                                <?= $filters['variety_id'] === (int) $variety['variety_id'] ? 'selected' : '' ?>>
                                <?= e($variety['variety_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="market_id">도시</label>
                    <select id="market_id" name="market_id">
                        <option value="">전체</option>
                        <?php foreach ($markets as $market): ?>
                            <option value="<?= e($market['market_id']) ?>"
                                <?= $filters['market_id'] === (int) $market['market_id'] ? 'selected' : '' ?>>
                                <?= e($market['market_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="grade">등급</label>
                    <select id="grade" name="grade">
                        <option value="">전체</option>
                        <?php foreach (GRADES as $grade): ?>
                            <option value="<?= e($grade) ?>" <?= $filters['grade'] === $grade ? 'selected' : '' ?>>
                                <?= e($grade) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="from">시작 월</label>
                    <input type="month" id="from" name="from" value="<?= e($filters['from'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="to">끝 월</label>
                    <input type="month" id="to" name="to" value="<?= e($filters['to'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="min_price">평균가 (원)</label>
                    <div class="range">
                        <input type="number" id="min_price" name="min_price" min="0" step="100"
                               placeholder="최소" value="<?= e($filters['min_price'] ?? '') ?>">
                        <span>~</span>
                        <input type="number" name="max_price" min="0" step="100" aria-label="최대 평균가"
                               placeholder="최대" value="<?= e($filters['max_price'] ?? '') ?>">
                    </div>
                </div>
                <div class="field">
                    <label for="sort">정렬</label>
                    <select id="sort" name="sort">
                        <?php foreach (SORTS as $key => [$label]): ?>
                            <option value="<?= e($key) ?>" <?= $sort === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="actions">
                <a class="btn btn-light" href="list.php">초기화</a>
                <button type="submit" class="btn btn-primary">검색</button>
            </div>
        </form>

        <section class="card">
            <div class="result-head">
                <p class="count">검색 결과 <strong><?= number_format($total) ?></strong>건
                    <?php if ($total > 0): ?>· <?= $page ?> / <?= $totalPages ?> 페이지<?php endif; ?></p>
                <?php if ($loggedIn && $rows): ?>
                    <div class="tools">
                        <button type="submit" form="bulk-delete" class="btn btn-danger btn-sm" id="bulk-delete-button" disabled>
                            선택 삭제 (<span id="selected-count">0</span>)
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!$rows): ?>
                <p class="empty">조건에 맞는 자료가 없습니다.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <?php if ($loggedIn): ?>
                                    <th class="check"><input type="checkbox" id="check-all" aria-label="전체 선택"></th>
                                <?php endif; ?>
                                <th>연월</th>
                                <th>품목</th>
                                <th>품종</th>
                                <th>도시</th>
                                <th>등급</th>
                                <th>단위</th>
                                <th class="num">평균가</th>
                                <th class="num">최저가</th>
                                <th class="num">최고가</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <?php if ($loggedIn): ?>
                                        <td class="check">
                                            <input type="checkbox" class="row-check" form="bulk-delete" name="ids[]"
                                                   value="<?= e($row['price_id']) ?>" aria-label="선택">
                                        </td>
                                    <?php endif; ?>
                                    <td><?= e(substr($row['ym'], 0, 7)) ?></td>
                                    <td><?= e($row['fruit_name']) ?></td>
                                    <td><?= e($row['variety_name']) ?></td>
                                    <td><?= e($row['market_name']) ?></td>
                                    <td><span class="badge"><?= e($row['grade']) ?></span></td>
                                    <td><?= e($row['unit']) ?></td>
                                    <td class="num"><?= e(format_won($row['avg_price'])) ?></td>
                                    <td class="num"><?= e(format_won($row['min_price'])) ?></td>
                                    <td class="num"><?= e(format_won($row['max_price'])) ?></td>
                                    <td class="manage">
                                        <a class="btn btn-light btn-sm"
                                           href="view.php?<?= e(http_build_query(['id' => $row['price_id'], 'return' => $currentUrl])) ?>">상세</a>
                                        <?php if ($loggedIn): ?>
                                            <!-- 수정은 이혜령 님의 price_update.php로 연결 -->
                                            <a class="btn btn-light btn-sm"
                                               href="price_update.php?id=<?= e($row['price_id']) ?>">수정</a>
                                            <button type="submit" form="delete-<?= e($row['price_id']) ?>"
                                                    class="btn btn-danger btn-sm js-confirm-delete">삭제</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($loggedIn): ?>
                    <!-- 선택 삭제 폼: 체크박스가 form="bulk-delete"로 연결됩니다. -->
                    <form id="bulk-delete" method="post" action="delete.php">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="return" value="<?= e($currentUrl) ?>">
                    </form>
                    <!-- 한 건 삭제 폼: 행마다 하나씩 -->
                    <?php foreach ($rows as $row): ?>
                        <form id="delete-<?= e($row['price_id']) ?>" method="post" action="delete.php">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="return" value="<?= e($currentUrl) ?>">
                            <input type="hidden" name="ids[]" value="<?= e($row['price_id']) ?>">
                        </form>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if ($totalPages > 1): ?>
                    <nav class="pagination" aria-label="페이지">
                        <?php if ($page > 1): ?>
                            <a href="<?= e(list_url($filters, $sort, $page - 1)) ?>">이전</a>
                        <?php endif; ?>
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        if ($start > 1) {
                            echo '<a href="' . e(list_url($filters, $sort, 1)) . '">1</a>';
                            if ($start > 2) {
                                echo '<span class="gap">…</span>';
                            }
                        }
                        for ($number = $start; $number <= $end; $number++) {
                            if ($number === $page) {
                                echo '<span class="current">' . $number . '</span>';
                            } else {
                                echo '<a href="' . e(list_url($filters, $sort, $number)) . '">' . $number . '</a>';
                            }
                        }
                        if ($end < $totalPages) {
                            if ($end < $totalPages - 1) {
                                echo '<span class="gap">…</span>';
                            }
                            echo '<a href="' . e(list_url($filters, $sort, $totalPages)) . '">' . $totalPages . '</a>';
                        }
                        ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="<?= e(list_url($filters, $sort, $page + 1)) ?>">다음</a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <script>
            // 품목을 고르면 그 품목의 품종만 보이게 합니다.
            (function () {
                var fruit = document.getElementById('fruit_id');
                var variety = document.getElementById('variety_id');
                function syncVarieties() {
                    var fruitId = fruit.value;
                    Array.prototype.forEach.call(variety.options, function (option) {
                        if (!option.dataset.fruit) { return; }
                        var visible = fruitId === '' || option.dataset.fruit === fruitId;
                        option.hidden = !visible;
                        option.disabled = !visible;
                        if (!visible && option.selected) { variety.value = ''; }
                    });
                }
                fruit.addEventListener('change', syncVarieties);
                syncVarieties();
            })();

            // 체크박스 선택 개수, 전체 선택, 삭제 확인
            (function () {
                var checks = document.querySelectorAll('.row-check');
                var checkAll = document.getElementById('check-all');
                var bulkButton = document.getElementById('bulk-delete-button');
                var countLabel = document.getElementById('selected-count');
                function update() {
                    var selected = document.querySelectorAll('.row-check:checked').length;
                    if (countLabel) { countLabel.textContent = selected; }
                    if (bulkButton) { bulkButton.disabled = selected === 0; }
                    if (checkAll) { checkAll.checked = selected > 0 && selected === checks.length; }
                }
                Array.prototype.forEach.call(checks, function (box) { box.addEventListener('change', update); });
                if (checkAll) {
                    checkAll.addEventListener('change', function () {
                        Array.prototype.forEach.call(checks, function (box) { box.checked = checkAll.checked; });
                        update();
                    });
                }
                if (bulkButton) {
                    bulkButton.addEventListener('click', function (event) {
                        var selected = document.querySelectorAll('.row-check:checked').length;
                        if (!confirm('선택한 ' + selected + '건을 삭제할까요? 되돌릴 수 없습니다.')) { event.preventDefault(); }
                    });
                }
                Array.prototype.forEach.call(document.querySelectorAll('.js-confirm-delete'), function (button) {
                    button.addEventListener('click', function (event) {
                        if (!confirm('이 자료를 삭제할까요? 되돌릴 수 없습니다.')) { event.preventDefault(); }
                    });
                });
                update();
            })();
        </script>
<?php
crud_page_end();