<?php
// Lab 3 - WITH Composite (GOOD starter, live API, has TODOs)
interface ForumComponent {
    public function display(int $depth = 0): void;
}
class Post implements ForumComponent {
    public function __construct(private string $author, private string $message) {}
    public function display(int $depth = 0): void {
        $indent = str_repeat("  ", $depth);
        echo $indent . "- Post by {$this->author}: {$this->message}\n";
    }
}
class Thread implements ForumComponent {
    /** @var ForumComponent[] */
    private array $children = [];
    public function __construct(private string $title) {}
    public function add(ForumComponent $c): void { $this->children[] = $c; }
    public function display(int $depth = 0): void {
        $indent = str_repeat("  ", $depth);
        echo $indent . "+ Thread: {$this->title}\n";
        foreach ($this->children as $child) {
            $child->display($depth + 1);
        }
    }
    public static function fromApi(int $postId): self {
        $postJson = file_get_contents("https://jsonplaceholder.typicode.com/posts/$postId");
        $post = json_decode($postJson, true);
        $thread = new self($post["title"]);
        $thread->add(new Post("Author {$post['userId']}", substr($post["body"],0,40)."..."));
        $commentsJson = file_get_contents("https://jsonplaceholder.typicode.com/posts/$postId/comments");
        $comments = json_decode($commentsJson, true);
        $replies = new Thread("Replies");
        foreach (array_slice($comments,0,2) as $c) $replies->add(new Post($c["email"], substr($c["body"],0,30)."..."));
        $thread->add($replies);
        return $thread;
    }
}

// Bundle: Pre-set combo ForumComponent (leaf-like, 1 class, no client changes)
class Bundle implements ForumComponent {
    /** @var ForumComponent[] */
    private array $children = [];
    public function __construct(string $name, array $components) {
        $this->children = $components;
    }
    public function add(ForumComponent $c): void {
        // Bundle is a pre-set combo - not meant to be extended externally
        // Could throw or silently ignore; here we ignore to maintain leaf-like behavior
    }
    public function display(int $depth = 0): void {
        $indent = str_repeat("  ", $depth);
        echo $indent . "~ Bundle\n";
        foreach ($this->children as $child) {
            $child->display($depth + 1);
        }
    }
}
// TODO: Create RAG variant: Document/Section/Chunk with getText() and embed()
interface RagComponent {
    public function getText(): string;
    public function embed(): array; // returns vector representation
}

class Chunk implements RagComponent {
    public function __construct(private string $text) {}
    public function getText(): string { return $this->text; }
    public function embed(): array { return [strlen($this->text) % 100]; } // mock embedding
}

class Section implements RagComponent {
    /** @var RagComponent[] */
    private array $children = [];
    public function __construct(private string $title) {}
    public function add(RagComponent $c): void { $this->children[] = $c; }
    public function getText(): string {
        $parts = [$this->title];
        foreach ($this->children as $c) $parts[] = $c->getText();
        return implode("\n", $parts);
    }
    public function embed(): array {
        $vec = [strlen($this->title) % 100];
        foreach ($this->children as $c) $vec = array_merge($vec, $c->embed());
        return $vec;
    }
}

class Document implements RagComponent {
    /** @var RagComponent[] */
    private array $children = [];
    public function __construct(private string $title) {}
    public function add(RagComponent $c): void { $this->children[] = $c; }
    public function getText(): string {
        $parts = ["Document: {$this->title}"];
        foreach ($this->children as $c) $parts[] = $c->getText();
        return implode("\n\n", $parts);
    }
    public function embed(): array {
        $vec = [strlen($this->title) % 100];
        foreach ($this->children as $c) $vec = array_merge($vec, $c->embed());
        return $vec;
    }
}
if (basename(__FILE__)===basename($_SERVER['SCRIPT_FILENAME'])) {
    echo "WITH Composite (GOOD - complete TODOs):\n";
    $thread = Thread::fromApi(1); // live to jsonplaceholder.typicode.com
    $thread->display();
    
    echo "\n  --- RAG Variant Demo ---\n";
    $doc = new Document("Company Handbook");
    $sec1 = new Section("Policies");
    $sec1->add(new Chunk("Remote work policy: 3 days/week in office."));
    $sec1->add(new Chunk("Code review required for all PRs."));
    $sec2 = new Section("Benefits");
    $sec2->add(new Chunk("Health insurance: 100% covered."));
    $doc->add($sec1);
    $doc->add($sec2);
    
    echo $doc->getText() . "\n";
    echo "  Embedding dims: " . count($doc->embed()) . "\n";
    
    echo "\n  --- Bundle Demo (pre-set combo, 1 class, no client changes) ---\n";
    $bundle = new Bundle("Welcome Pack", [
        new Post("Admin", "Welcome to the forum!"),
        new Thread("Getting Started"),
        new Post("Bot", "Check the FAQ thread above."),
    ]);
    $bundle->display();
}
