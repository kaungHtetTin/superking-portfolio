<?php
namespace App\Controllers;

use Mini\Database as DB;

final class LuckyDrawController
{
    private function privatePage(): void
    {
        header('Cache-Control: no-store, private');
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow');
    }

    private function log(string $action, int $campaignId, string $label): void
    {
        DB::insert('admin_activity', ['actor' => DB::fetchValue('SELECT email FROM admins WHERE id=?', [$_SESSION['admin_id']]) ?: 'Administrator', 'action' => $action, 'content_type' => 'lucky_draw', 'record_id' => $campaignId, 'record_label' => mb_substr($label, 0, 255)]);
    }

    private function campaign(string $id, bool $lock = false): array
    {
        if (!ctype_digit($id) || (int)$id < 1) not_found();
        $row = DB::fetch('SELECT * FROM lucky_campaigns WHERE id=?'.($lock ? ' FOR UPDATE' : ''), [$id]);
        if (!$row) not_found();
        return $row;
    }

    private function text(string $key, int $limit): string
    {
        $value = $_POST[$key] ?? '';
        if (!is_string($value) || mb_strlen(trim($value)) > $limit) throw new \InvalidArgumentException('Check the '.str_replace('_', ' ', $key).'.');
        return trim($value);
    }

    private function number(mixed $value, int $max): int
    {
        if (!is_scalar($value) || filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value < 1 || (int)$value > $max) throw new \InvalidArgumentException('Quantities must be whole numbers between 1 and '.number_format($max).'.');
        return (int)$value;
    }

    private function fail(\InvalidArgumentException $error, string $path): never
    {
        redirect($path, $error->getMessage(), 'error');
    }

    public function index(): void
    {
        $this->privatePage();
        view('admin/lucky-index', ['title' => 'Lucky draw', 'campaigns' => DB::fetchAll('SELECT c.*, (SELECT COUNT(*) FROM lucky_codes x WHERE x.campaign_id=c.id AND x.revealed_at IS NOT NULL) played, (SELECT COUNT(*) FROM lucky_codes x WHERE x.campaign_id=c.id AND x.redeemed_at IS NOT NULL) collected FROM lucky_campaigns c ORDER BY c.id DESC')], 'layouts/admin');
    }

    public function create(): void
    {
        $this->privatePage();
        view('admin/lucky-create', ['title' => 'Create promotion'], 'layouts/admin');
    }

    public function store(): void
    {
        try {
            $name = $this->text('name', 190);
            $instructions = $this->text('instructions', 2000);
            $start = $this->text('starts_on', 10);
            $end = $this->text('ends_on', 10);
            foreach ([$start, $end] as $date) {
                $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
                if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new \InvalidArgumentException('Choose valid promotion dates.');
            }
            if ($name === '' || $instructions === '' || $end < $start) throw new \InvalidArgumentException('Enter a name, collection instructions and an end date on or after the start date.');
            $total = $this->number($_POST['total_codes'] ?? null, 10000);
            $names = $_POST['prize_name'] ?? [];
            $quantities = $_POST['prize_quantity'] ?? [];
            if (!is_array($names) || !is_array($quantities) || !$names || count($names) > 30 || count($names) !== count($quantities)) throw new \InvalidArgumentException('Add between 1 and 30 prizes.');
            $prizes = [];
            foreach ($names as $i => $prize) {
                if (!is_string($prize) || trim($prize) === '' || mb_strlen(trim($prize)) > 190) throw new \InvalidArgumentException('Enter a prize name of up to 190 characters for every row.');
                $prizes[] = ['name' => trim($prize), 'quantity' => $this->number($quantities[$i] ?? null, 10000)];
            }
            if (array_sum(array_column($prizes, 'quantity')) > $total) throw new \InvalidArgumentException('Winning prizes cannot exceed the total number of codes.');
            $id = DB::transaction(function () use ($name, $instructions, $start, $end, $total, $prizes) {
                $id = DB::insert('lucky_campaigns', ['name' => $name, 'instructions' => $instructions, 'starts_on' => $start, 'ends_on' => $end, 'total_codes' => $total]);
                foreach ($prizes as $prize) DB::insert('lucky_prizes', ['campaign_id' => $id] + $prize);
                $this->log('created', $id, $name);
                return $id;
            });
            unset($_SESSION['lucky_form']);
            redirect('admin/lucky-draw/'.$id, 'Promotion created. Generate your codes next.');
        } catch (\InvalidArgumentException $error) {
            $_SESSION['lucky_form'] = array_intersect_key($_POST, array_flip(['name','instructions','starts_on','ends_on','total_codes','prize_name','prize_quantity']));
            $this->fail($error, 'admin/lucky-draw/create');
        }
    }

    public function show(string $id): void
    {
        $this->privatePage();
        $campaign = $this->campaign($id);
        $prizes = DB::fetchAll('SELECT p.*, (SELECT COUNT(*) FROM lucky_codes x WHERE x.prize_id=p.id AND x.revealed_at IS NOT NULL) won, (SELECT COUNT(*) FROM lucky_codes x WHERE x.prize_id=p.id AND x.redeemed_at IS NOT NULL) collected FROM lucky_prizes p WHERE campaign_id=? ORDER BY id', [$id]);
        $stats = DB::fetch('SELECT COUNT(*) generated, COALESCE(SUM(revealed_at IS NOT NULL),0) played, COALESCE(SUM(prize_id IS NOT NULL AND revealed_at IS NOT NULL),0) winners, COALESCE(SUM(redeemed_at IS NOT NULL),0) collected FROM lucky_codes WHERE campaign_id=?', [$id]);
        $page = max(1, min((int)ceil(max(1, $stats['winners']) / 25), (int)input_text('page', 8)));
        $offset = ($page - 1) * 25;
        $winners = DB::fetchAll('SELECT x.id,x.code,x.revealed_at,x.redeemed_at,p.name prize_name FROM lucky_codes x JOIN lucky_prizes p ON p.id=x.prize_id WHERE x.campaign_id=? AND x.revealed_at IS NOT NULL ORDER BY x.id DESC LIMIT 25 OFFSET '.$offset, [$id]);
        view('admin/lucky-show', compact('campaign','prizes','stats','winners','page') + ['title' => $campaign['name']], 'layouts/admin');
    }

    public function generate(string $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                $campaign = $this->campaign($id, true);
                if ($campaign['generated_at']) throw new \InvalidArgumentException('Codes have already been generated. Use Print or Export to access the existing codes.');
                $allocation = [];
                foreach (DB::fetchAll('SELECT * FROM lucky_prizes WHERE campaign_id=?', [$id]) as $prize) {
                    for ($i = 0; $i < $prize['quantity']; $i++) $allocation[] = $prize['id'];
                }
                $allocation = array_pad($allocation, $campaign['total_codes'], null);
                for ($i = count($allocation) - 1; $i > 0; $i--) {
                    $j = random_int(0, $i);
                    [$allocation[$i], $allocation[$j]] = [$allocation[$j], $allocation[$i]];
                }
                $insert = DB::connection()->prepare('INSERT INTO lucky_codes (campaign_id,code,prize_id) VALUES (?,?,?)');
                foreach ($allocation as $prizeId) $insert->execute([$id, strtoupper(bin2hex(random_bytes(12))), $prizeId]);
                DB::update('lucky_campaigns', (int)$id, ['generated_at' => date('Y-m-d H:i:s')]);
                $this->log('generated codes', (int)$id, $campaign['name'].' / '.$campaign['total_codes'].' codes');
            });
            redirect('admin/lucky-draw/'.$id, 'Codes are ready. Print or export them, then activate the promotion.');
        } catch (\InvalidArgumentException $error) { $this->fail($error, 'admin/lucky-draw/'.$id); }
    }

    public function status(string $id): void
    {
        try {
            $status = $this->text('status', 12);
            if (!in_array($status, ['active','paused','closed'], true)) throw new \InvalidArgumentException('Choose a valid promotion status.');
            DB::transaction(function () use ($id, $status) {
                $campaign = $this->campaign($id, true);
                if (!$campaign['generated_at']) throw new \InvalidArgumentException('Generate codes before activating the promotion.');
                DB::update('lucky_campaigns', (int)$id, ['status' => $status]);
                $this->log('status: '.$status, (int)$id, $campaign['name']);
            });
            redirect('admin/lucky-draw/'.$id, 'Promotion '.$status.'.');
        } catch (\InvalidArgumentException $error) { $this->fail($error, 'admin/lucky-draw/'.$id); }
    }

    public function printCodes(string $id): void
    {
        $this->privatePage();
        $campaign = $this->campaign($id);
        $pages = max(1, (int)ceil($campaign['total_codes'] / 100));
        $page = max(1, min($pages, (int)input_text('page', 8)));
        $codes = DB::fetchAll('SELECT code FROM lucky_codes WHERE campaign_id=? ORDER BY id LIMIT 100 OFFSET '.(($page - 1) * 100), [$id]);
        view('admin/lucky-print', compact('campaign','codes','page','pages'), null);
    }

    public function export(string $id): void
    {
        $this->privatePage();
        $campaign = $this->campaign($id);
        $this->log('exported codes', (int)$id, $campaign['name']);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="promotion-'.$campaign['id'].'-codes.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Code','QR URL'], ',', '"', '');
        foreach (DB::fetchAll('SELECT code FROM lucky_codes WHERE campaign_id=? ORDER BY id', [$id]) as $row) {
            $link = $this->absoluteUrl('lucky-draw/'.$row['code']);
            fputcsv($out, [$row['code'], preg_match('/^[=+@\-\t\r\n]/', $link) ? "'".$link : $link], ',', '"', '');
        }
        fclose($out);
    }

    public function absoluteUrl(string $path): string
    {
        $link = url($path);
        if (preg_match('#^https?://#i', $link)) return $link;
        return ((!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https://' : 'http://').($_SERVER['HTTP_HOST'] ?? 'localhost').$link;
    }

    private function normalize(string $code): string
    {
        return strtoupper(str_replace(['-', ' '], '', trim($code)));
    }

    private function ticket(string $code): ?array
    {
        if (!preg_match('/^[A-F0-9]{24}$/D', $code)) return null;
        return DB::fetch('SELECT x.id code_id,x.code,x.revealed_at,x.redeemed_at,c.id campaign_id,c.name,c.instructions,c.starts_on,c.ends_on,c.status,p.name prize_name FROM lucky_codes x JOIN lucky_campaigns c ON c.id=x.campaign_id LEFT JOIN lucky_prizes p ON p.id=x.prize_id WHERE x.code=?', [$code]);
    }

    private function available(array $ticket): bool
    {
        $today = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Yangon')))->format('Y-m-d');
        return $ticket['status'] === 'active' && $ticket['starts_on'] <= $today && $ticket['ends_on'] >= $today;
    }

    public function entry(): void
    {
        $this->privatePage();
        view('site/lucky-draw', ['ticket' => null, 'entered' => false, 'available' => false], null);
    }

    public function lookup(): void
    {
        $code = $this->normalize(input_text('code', 100, true));
        if (!preg_match('/^[A-F0-9]{24}$/D', $code)) redirect('lucky-draw?lang='.language(), 'Enter the complete 24-character code from your label.', 'error');
        redirect('lucky-draw/'.$code.'?lang='.language());
    }

    public function play(string $code): void
    {
        $this->privatePage();
        $code = $this->normalize($code);
        $ticket = $this->ticket($code);
        if (!$ticket) http_response_code(404);
        // Never pass an unrevealed prize to the public template.
        if ($ticket && !$ticket['revealed_at']) $ticket['prize_name'] = null;
        view('site/lucky-draw', ['ticket' => $ticket, 'entered' => true, 'available' => $ticket && $this->available($ticket)], null);
    }

    public function reveal(string $code): void
    {
        $code = $this->normalize($code);
        $ticket = $this->ticket($code);
        if (!$ticket) { $this->play($code); return; }
        try {
            $available = DB::transaction(function () use ($ticket, $code) {
                $campaign = $this->campaign((string)$ticket['campaign_id'], true);
                $current = DB::fetch('SELECT revealed_at FROM lucky_codes WHERE id=? FOR UPDATE', [$ticket['code_id']]);
                // Previously revealed codes retain their result and first location forever.
                if ($current['revealed_at']) return true;
                if (!$this->available($campaign)) return false;
                $location = $this->submittedLocation();
                DB::insert('lucky_locations', ['code_id' => $ticket['code_id']] + $location);
                DB::statement('UPDATE lucky_codes SET revealed_at=? WHERE code=?', [date('Y-m-d H:i:s'), $code]);
                return true;
            });
            redirect('lucky-draw/'.$code.'?lang='.language(), $available ? null : 'This promotion is not accepting plays right now.', 'warning');
        } catch (\InvalidArgumentException $error) {
            redirect('lucky-draw/'.$code.'?lang='.language(), $error->getMessage(), 'error');
        }
    }

    private function submittedLocation(): array
    {
        if (($_POST['location_consent'] ?? '') !== 'reveal-location-v1') {
            throw new \InvalidArgumentException(t('Allow location access before revealing your prize. Your code has not been used.', 'ဆုရလဒ်မကြည့်မီ တည်နေရာအသုံးပြုခွင့်ကို ခွင့်ပြုပေးပါ။ သင့်ကုဒ်ကို မသုံးရသေးပါ။'));
        }
        $values = [];
        foreach (['latitude' => [-90, 90], 'longitude' => [-180, 180], 'accuracy_m' => [0, 10000000]] as $key => [$min, $max]) {
            $raw = $_POST[$key] ?? null;
            if (!is_scalar($raw) || !is_numeric($raw) || !is_finite((float)$raw) || (float)$raw < $min || (float)$raw > $max) {
                throw new \InvalidArgumentException(t('A valid device location is required. Enable location services and try again. Your code has not been used.', 'မှန်ကန်သောတည်နေရာ လိုအပ်ပါသည်။ တည်နေရာဝန်ဆောင်မှုကို ဖွင့်ပြီး ထပ်မံကြိုးစားပါ။ သင့်ကုဒ်ကို မသုံးရသေးပါ။'));
            }
            $values[$key] = round((float)$raw, $key === 'accuracy_m' ? 2 : 7);
        }
        return $values + ['consent_version' => 'reveal-location-v1', 'recorded_at' => gmdate('Y-m-d H:i:s')];
    }

    private function locationFilters(): array
    {
        $campaign = input_text('campaign', 20);
        $from = input_text('from', 20);
        $to = input_text('to', 20);
        $where = '1=1';
        $bindings = [];
        $errors = [];
        if ($campaign !== '') {
            if (!ctype_digit($campaign) || (int)$campaign < 1) $errors[] = 'Choose a valid promotion.';
            else { $where .= ' AND c.id=?'; $bindings[] = $campaign; }
        }
        foreach (['from' => $from, 'to' => $to] as $key => $date) {
            if ($date === '') continue;
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('Asia/Yangon'));
            if (!$parsed || $parsed->format('Y-m-d') !== $date) { $errors[] = 'Choose a valid '.$key.' date.'; continue; }
            if ($key === 'to') $parsed = $parsed->modify('+1 day');
            $where .= $key === 'from' ? ' AND l.recorded_at>=?' : ' AND l.recorded_at<?';
            $bindings[] = $parsed->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        }
        if ($from !== '' && $to !== '' && $from > $to) $errors[] = 'The last date must be on or after the first date.';
        // Invalid filters must never silently broaden a report or export.
        if ($errors) $where .= ' AND 1=0';
        return compact('campaign', 'from', 'to', 'where', 'bindings', 'errors');
    }

    public function locations(): void
    {
        $this->privatePage();
        // Tile requests need an origin referrer; query strings and coordinates stay private.
        header('Referrer-Policy: strict-origin-when-cross-origin');
        $filters = $this->locationFilters();
        ['where' => $where, 'bindings' => $bindings] = $filters;
        $join = ' FROM lucky_locations l JOIN lucky_codes x ON x.id=l.code_id JOIN lucky_campaigns c ON c.id=x.campaign_id';
        $total = (int)DB::fetchValue('SELECT COUNT(*)'.$join.' WHERE '.$where, $bindings);
        $pages = max(1, (int)ceil($total / 50));
        $page = max(1, min($pages, (int)input_text('page', 8)));
        $offset = ($page - 1) * 50;
        $rows = DB::fetchAll('SELECT l.*,RIGHT(x.code,6) code_ending,c.name campaign_name'.$join.' WHERE '.$where.' ORDER BY l.recorded_at DESC,l.id DESC LIMIT 50 OFFSET '.$offset, $bindings);
        // Co-located records share a marker. Every filtered record is represented.
        $points = DB::fetchAll('SELECT l.latitude,l.longitude,COUNT(*) total'.$join.' WHERE '.$where.' GROUP BY l.latitude,l.longitude', $bindings);
        $campaigns = DB::fetchAll('SELECT id,name FROM lucky_campaigns ORDER BY id DESC');
        view('admin/lucky-locations', compact('filters','total','pages','page','rows','points','campaigns') + ['title' => 'Product distribution'], 'layouts/admin');
    }

    public function exportLocations(): void
    {
        $this->privatePage();
        $filters = $this->locationFilters();
        if ($filters['errors']) { redirect('admin/distribution?'.http_build_query(array_intersect_key($filters, array_flip(['campaign','from','to']))), implode(' ', $filters['errors']), 'error'); }
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="product-distribution.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Promotion','Code ending','Latitude','Longitude','Accuracy (metres)','Recorded (Myanmar time)'], ',', '"', '');
        $query = DB::statement('SELECT c.name,RIGHT(x.code,6) code_ending,l.latitude,l.longitude,l.accuracy_m,l.recorded_at FROM lucky_locations l JOIN lucky_codes x ON x.id=l.code_id JOIN lucky_campaigns c ON c.id=x.campaign_id WHERE '.$filters['where'].' ORDER BY l.recorded_at DESC,l.id DESC', $filters['bindings']);
        while ($row = $query->fetch()) {
            $name = preg_match('/^[=+@\-\t\r\n]/', ltrim($row['name'])) ? "'".$row['name'] : $row['name'];
            $when = (new \DateTimeImmutable($row['recorded_at'], new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Asia/Yangon'))->format('Y-m-d H:i:s');
            fputcsv($out, [$name,$row['code_ending'],$row['latitude'],$row['longitude'],$row['accuracy_m'],$when], ',', '"', '');
        }
        fclose($out);
        $this->log('exported locations', 0, 'Product distribution report');
    }

    public function redeem(): void
    {
        $this->privatePage();
        $ticket = $this->ticket($this->normalize(input_text('code', 100)));
        if (input_text('code', 100) !== '' && !$ticket) $_SESSION['flash'] = ['message' => 'Code not found. Check the original label.', 'type' => 'error'];
        view('admin/lucky-redeem', ['title' => 'Give a prize', 'ticket' => $ticket], 'layouts/admin');
    }

    public function collect(string $code): void
    {
        $code = $this->normalize($code);
        DB::transaction(function () use ($code) {
            $ticket = $this->ticket($code);
            if (!$ticket) not_found();
            $updated = DB::statement('UPDATE lucky_codes SET redeemed_at=?,redeemed_by=? WHERE code=? AND prize_id IS NOT NULL AND revealed_at IS NOT NULL AND redeemed_at IS NULL', [date('Y-m-d H:i:s'), $_SESSION['admin_id'], $code])->rowCount();
            // Use the conditional update result to make redemption safe across staff sessions.
            if ($updated === 1) {
                $this->log('prize collected', (int)$ticket['campaign_id'], $ticket['name'].' / '.$ticket['prize_name']);
                $_SESSION['flash'] = ['message' => 'Prize marked as collected.', 'type' => 'success'];
            } else {
                $_SESSION['flash'] = ['message' => 'No change: this code is already collected, has not been revealed, or did not win.', 'type' => 'warning'];
            }
        });
        redirect('admin/lucky-draw/redeem?code='.$code);
    }
}
