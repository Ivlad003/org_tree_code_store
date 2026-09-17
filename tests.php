<?php
// Self-check for the tree rules that keep the chart renderable. Run: php tests.php
// d3-org-chart renders NOTHING on multiple roots or a cycle, so these are the
// invariants that turn a blank page for every viewer into a rejected form.

declare(strict_types=1);

// Point the DB at a throwaway file before src/db.php resolves projectRoot().
$tmp = sys_get_temp_dir() . '/org_tree_test_' . getmypid();
mkdir($tmp . '/data', 0777, true);
mkdir($tmp . '/uploads/avatars', 0777, true);
copy(__DIR__ . '/src/db.php', $tmp . '/db_copy.php');
mkdir($tmp . '/src', 0777, true);
rename($tmp . '/db_copy.php', $tmp . '/src/db.php');
require $tmp . '/src/db.php';

register_shutdown_function(function () use ($tmp) {
    array_map('unlink', glob($tmp . '/data/*') ?: []);
    @rmdir($tmp . '/data'); @rmdir($tmp . '/uploads/avatars'); @rmdir($tmp . '/uploads');
    @unlink($tmp . '/src/db.php'); @rmdir($tmp . '/src'); @rmdir($tmp);
});

$pass = 0; $fail = 0;
function check(string $name, callable $fn): void {
    global $pass, $fail;
    try { $fn(); echo "PASS  $name\n"; $GLOBALS['pass']++; }
    catch (Throwable $e) { echo "FAIL  $name — {$e->getMessage()}\n"; $GLOBALS['fail']++; }
}
function throws(callable $fn, string $why): void {
    try { $fn(); } catch (ValidationError $e) { return; }
    throw new RuntimeException($why);
}

$root = saveDepartment(['name' => 'Root', 'parent_id' => '']);
$kid  = saveDepartment(['name' => 'Kid',  'parent_id' => (string)$root]);
$emp  = saveEmployee(['first_name' => 'Ann', 'parent_id' => (string)$kid]);

check('a second root is rejected', fn() =>
    throws(fn() => saveDepartment(['name' => 'Other', 'parent_id' => '']), 'second root was accepted'));

check('the existing root may stay parentless', function () use ($root) {
    saveDepartment(['id' => $root, 'name' => 'Root', 'parent_id' => '']);
});

check('a node cannot be its own parent', fn() =>
    throws(fn() => saveDepartment(['id' => $kid, 'name' => 'Kid', 'parent_id' => (string)$kid]), 'self-parent accepted'));

check('a cycle is rejected', fn() =>
    throws(fn() => saveDepartment(['id' => $root, 'name' => 'Root', 'parent_id' => (string)$kid]), 'cycle accepted'));

check('a missing parent is rejected', fn() =>
    throws(fn() => saveEmployee(['first_name' => 'Ghost', 'parent_id' => '999999']), 'ghost parent accepted'));

check('an empty name is rejected', function () {
    throws(fn() => saveEmployee(['first_name' => '   ', 'parent_id' => '1']), 'empty employee name accepted');
    throws(fn() => saveDepartment(['name' => '', 'parent_id' => '1']), 'empty department name accepted');
});

check('deleting a node promotes its children to the grandparent', function () use ($kid, $emp, $root) {
    deleteDepartment($kid);
    $row = getEmployee($emp);
    if ((int)$row['parent_id'] !== $root) {
        throw new RuntimeException("child went to {$row['parent_id']}, expected {$root}");
    }
});

check('deleting the root is refused while it has children', fn() =>
    throws(fn() => deleteDepartment($root), 'root deleted out from under its children'));

check('every node reaches the single root', function () {
    $map = parentMap();
    $roots = array_keys(array_filter($map, fn($p) => $p === null));
    if (count($roots) !== 1) throw new RuntimeException('roots: ' . count($roots));
    foreach (array_keys($map) as $id) {
        $seen = [];
        for ($cur = $id; $cur !== null; $cur = $map[$cur] ?? null) {
            if (isset($seen[$cur])) throw new RuntimeException("cycle at {$id}");
            $seen[$cur] = true;
        }
    }
});

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
