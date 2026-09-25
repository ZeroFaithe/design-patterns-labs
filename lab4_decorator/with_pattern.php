<?php
// Lab 4 - WITH Decorator (GOOD starter, live API, has TODOs)
interface HttpClient {
    public function get(string $url): string;
}
class BaseHttpClient implements HttpClient {
    public function get(string $url): string {
        // Live to real endpoint
        return file_get_contents($url);
    }
}
abstract class HttpDecorator implements HttpClient {
    public function __construct(protected HttpClient $wrapped) {}
}
class LoggingDecorator extends HttpDecorator {
    public function get(string $url): string {
        echo "[Log] GET $url\n";
        $r = $this->wrapped->get($url);
        echo "[Log] Got " . strlen($r) . " bytes\n";
        return $r;
    }
}
class CachingDecorator extends HttpDecorator {
    private array $cache = [];
    public function get(string $url): string {
        if (isset($this->cache[$url])) { echo "[Cache] Hit $url\n"; return $this->cache[$url]; }
        $r = $this->wrapped->get($url);
        $this->cache[$url] = $r;
        echo "[Cache] Stored $url\n";
        return $r;
    }
}
// TODO: Implement RetryDecorator (3 tries, catch Exception)
class RetryDecorator extends HttpDecorator {
    public function get(string $url): string {
        for ($i = 0; $i < 3; $i++) {
            try {
                return $this->wrapped->get($url);
            } catch (Exception $e) {
                if ($i === 2) throw $e;
                echo "[Retry] Attempt " . ($i + 1) . " failed, retrying...\n";
            }
        }
        throw new RuntimeException("Retry exhausted");
    }
}
// TODO: Implement TokenCounterDecorator
class TokenCounterDecorator extends HttpDecorator {
    public function get(string $url): string {
        $r = $this->wrapped->get($url);
        echo "[Tokens] " . str_word_count($r) . "\n";
        return $r;
    }
}
if (basename(__FILE__)===basename($_SERVER['SCRIPT_FILENAME'])) {
    echo "WITH Decorator (GOOD - complete TODOs):\n";
    $client = new BaseHttpClient();
    $client = new LoggingDecorator($client);
    $client = new CachingDecorator($client);
    $url = "https://jsonplaceholder.typicode.com/posts/1";
    echo substr($client->get($url),0,60) . "...\n"; // live
    echo substr($client->get($url),0,60) . "...\n"; // cached
    
    echo "\n  --- RetryDecorator Demo ---\n";
    $retryClient = new RetryDecorator(new BaseHttpClient());
    echo substr($retryClient->get($url), 0, 60) . "...\n";
    
    echo "\n  --- TokenCounterDecorator Demo ---\n";
    $tokenClient = new TokenCounterDecorator(new BaseHttpClient());
    echo substr($tokenClient->get($url), 0, 60) . "...\n";
    
    echo "\n  --- Full Stack: Retry + TokenCounter + Caching + Logging ---\n";
    $fullStack = new BaseHttpClient();
    $fullStack = new LoggingDecorator($fullStack);
    $fullStack = new CachingDecorator($fullStack);
    $fullStack = new RetryDecorator($fullStack);
    $fullStack = new TokenCounterDecorator($fullStack);
    echo substr($fullStack->get($url), 0, 60) . "...\n";
    
    echo "\n  --- Missing 3: Order Matters (Logging+Caching vs Caching+Logging) ---\n";
    echo "\n  A) Logging(Caching(Base)) - Log wraps Cache:\n";
    $orderA = new BaseHttpClient();
    $orderA = new CachingDecorator($orderA);
    $orderA = new LoggingDecorator($orderA);
    echo "  First call:\n";
    echo substr($orderA->get($url), 0, 60) . "...\n";
    echo "  Second call (cached):\n";
    echo substr($orderA->get($url), 0, 60) . "...\n";
    echo "  ^ Log appears on BOTH calls (cache hit still logs)\n";
    
    echo "\n  B) Caching(Logging(Base)) - Cache wraps Log:\n";
    $orderB = new BaseHttpClient();
    $orderB = new LoggingDecorator($orderB);
    $orderB = new CachingDecorator($orderB);
    echo "  First call:\n";
    echo substr($orderB->get($url), 0, 60) . "...\n";
    echo "  Second call (cached):\n";
    echo substr($orderB->get($url), 0, 60) . "...\n";
    echo "  ^ Log appears ONLY on first call (cache hit skips log)\n";
    
    echo "\n  --- Missing 4: 4 Decorators = 16 Combos with 5 Classes ---\n";
    $decorators = [
        'Logging' => fn($c) => new LoggingDecorator($c),
        'Caching' => fn($c) => new CachingDecorator($c),
        'Retry'   => fn($c) => new RetryDecorator($c),
        'Tokens'  => fn($c) => new TokenCounterDecorator($c),
    ];
    $names = array_keys($decorators);
    $count = 0;
    // Generate all 2^4 = 16 combinations
    for ($mask = 0; $mask < 16; $mask++) {
        $client = new BaseHttpClient();
        $combo = [];
        for ($i = 0; $i < 4; $i++) {
            if ($mask & (1 << $i)) {
                $client = $decorators[$names[$i]]($client);
                $combo[] = $names[$i];
            }
        }
        $count++;
        $label = $combo ? implode('→', $combo) : 'Base only';
        // Just show first few combos to avoid spam
        if ($count <= 5 || $mask === 15) {
            echo "  Combo $count/16: $label\n";
        } elseif ($count === 6) {
            echo "  ... (10 more combos) ...\n";
        }
    }
    echo "  Total: 4 decorators + 1 BaseHttpClient = 5 classes for 16 combos\n";
}
