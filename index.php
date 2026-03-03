<?php
session_start();

if (!isset($_SESSION['cards'])) {
    $_SESSION['cards'] = [];
}

// 1. PHP Safe Add Logic
if (isset($_POST['add_card'])) {
    $raw_input = $_POST['questions_input'] ?? ''; 
    $input = trim($raw_input);

    if (!empty($input)) {
        $lines = explode("\n", str_replace("\r", "", $input));
        foreach ($lines as $line) {
            $clean_question = trim($line);
            if (!empty($clean_question)) {
                $_SESSION['cards'][] = [
                    'id' => uniqid(),
                    'question' => htmlspecialchars($clean_question),
                    'taken' => false
                ];
            }
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// NEW: Save Edited Content Logic
if (isset($_POST['save_edit'])) {
    $id = $_POST['card_id'];
    $new_content = trim($_POST['new_content']);
    foreach ($_SESSION['cards'] as &$card) {
        if ($card['id'] === $id) {
            $card['question'] = htmlspecialchars($new_content);
        }
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// 2. Action Handlers
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $_SESSION['cards'] = array_filter($_SESSION['cards'], function($card) use ($id) {
        return $card['id'] !== $id;
    });
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_GET['mark_taken'])) {
    $id = $_GET['mark_taken'];
    foreach ($_SESSION['cards'] as &$card) {
        if ($card['id'] === $id) {
            $card['taken'] = !($card['taken']);
        }
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_POST['unlock_all'])) {
    foreach ($_SESSION['cards'] as &$card) {
        $card['taken'] = false;
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_POST['clear_all'])) {
    $_SESSION['cards'] = [];
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NovaCards - Flip System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; }

        .card-container { perspective: 1000px; min-height: 280px; width: 100%; }
        .card-inner {
            position: relative; width: 100%; height: 100%; min-height: 280px;
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            transform-style: preserve-3d;
        }
        .card-inner.flipped { transform: rotateY(180deg); }
        .card-front, .card-back {
            position: absolute; width: 100%; height: 100%; min-height: 280px;
            -webkit-backface-visibility: hidden; backface-visibility: hidden;
            border-radius: 1.25rem; padding: 1.5rem;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
        }
        .card-back { transform: rotateY(180deg); }

        /* LONG PRESS PROGRESS BAR */
        .hold-indicator {
            position: absolute; top: 0; left: 0; height: 6px;
            background: #f59e0b; width: 0%; transition: width 0.1s linear;
            z-index: 50; border-radius: 1.25rem 1.25rem 0 0;
        }

        .editing-mode { border: 2px solid #f59e0b !important; }

        /* Animations from your original code */
        @keyframes shuffleCardContent {
            0% { transform: rotateY(0deg) scale(1); }
            35% { transform: rotateY(8deg) scale(1.01); }
            65% { transform: rotateY(-8deg) scale(1.01); }
            100% { transform: rotateY(0deg) scale(1); }
        }
        .card-inner.content-shuffling { animation: shuffleCardContent 560ms ease-in-out; }
        .custom-scroll::-webkit-scrollbar { width: 4px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.2); border-radius: 10px; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen p-4 md:p-6 lg:p-12">

    <div class="max-w-6xl mx-auto w-full">
        <div id="novacardsView" class="w-full">
            <header class="text-center mb-8 md:mb-10">
                <h1 class="text-4xl md:text-5xl font-black text-indigo-500 mb-2">NovaCards</h1>
                <p class="text-slate-500 text-sm md:text-base">Hold a flipped card for 3 seconds to edit its content.</p>
                <p class="text-slate-600 text-xs md:text-sm mt-2 uppercase tracking-widest font-bold">Developed by Oalden Morales</p>
            </header>

            <div class="bg-slate-900 p-4 md:p-6 rounded-2xl border border-slate-800 shadow-2xl mb-8 w-full">
                <form method="POST" class="w-full">
                    <textarea name="questions_input" rows="3"
                        class="w-full p-3 rounded-xl bg-slate-800 border border-slate-700 focus:ring-2 focus:ring-indigo-500 focus:outline-none mb-4"
                        placeholder="Type questions here (one per line)..."></textarea>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" name="add_card" class="bg-indigo-600 hover:bg-indigo-500 px-6 py-2 rounded-xl font-bold transition-all">Create Cards</button>
                        <button type="button" onclick="randomSelect()" class="bg-amber-500 text-slate-950 px-6 py-2 rounded-xl font-black">🎲 Random</button>
                        <button type="submit" name="unlock_all" class="text-slate-400 border border-slate-800 px-4 py-2 rounded-xl hover:bg-slate-800 transition">🔄 Unlock All</button>
                    </div>
                </form>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 w-full" id="cardGrid">
                <?php foreach (array_values($_SESSION['cards']) as $index => $card): ?>
                    <div class="card-container w-full" data-taken="<?php echo $card['taken'] ? 'true' : 'false'; ?>">
                        <div class="card-inner <?php echo $card['taken'] ? 'cursor-not-allowed' : 'cursor-pointer'; ?>" 
                             onclick="handleCardClick(this, event)">

                            <div class="card-front bg-slate-900 border-2 <?php echo $card['taken'] ? 'border-emerald-500/30 opacity-40 grayscale' : 'border-slate-800 shadow-xl'; ?>">
                                <span class="absolute top-4 left-5 text-xl font-bold text-slate-600">Q#<?php echo $index + 1; ?></span>
                                <?php if ($card['taken']): ?>
                                    <div class="text-emerald-500 text-center"><div class="text-3xl font-black">✓</div><div class="text-sm font-bold">TAKEN</div></div>
                                <?php else: ?>
                                    <p class="text-slate-600 text-sm font-semibold">Click to view question</p>
                                <?php endif; ?>
                            </div>

                            <div class="card-back bg-gradient-to-br from-indigo-700 to-indigo-900 shadow-2xl overflow-hidden relative"
                                 onmousedown="startHold(this)" onmouseup="clearHold()" onmouseleave="clearHold()"
                                 ontouchstart="startHold(this)" ontouchend="clearHold()">
                                
                                <div class="hold-indicator"></div>

                                <div class="content-display w-full h-full flex flex-col items-center justify-center p-4">
                                    <p class="text-center text-lg md:text-xl font-medium leading-snug">
                                        <?php echo $card['question']; ?>
                                    </p>
                                    <div class="absolute bottom-4 text-[9px] uppercase tracking-widest text-indigo-300 font-bold opacity-50 px-4 text-center">
                                        Tap to hide • Hold 3s to Edit
                                    </div>
                                </div>

                                <form method="POST" class="edit-form hidden w-full h-full p-4 flex flex-col justify-center bg-slate-900" onclick="event.stopPropagation()">
                                    <input type="hidden" name="card_id" value="<?php echo $card['id']; ?>">
                                    <textarea name="new_content" class="w-full bg-slate-800 text-white p-3 rounded-lg border border-amber-500 focus:outline-none mb-3 text-sm" rows="4"><?php echo $card['question']; ?></textarea>
                                    <div class="flex gap-2">
                                        <button type="submit" name="save_edit" class="flex-1 bg-amber-500 text-slate-900 py-2 rounded-lg font-bold text-xs">Save Changes</button>
                                        <button type="button" onclick="cancelEdit(this)" class="flex-1 bg-slate-700 text-white py-2 rounded-lg font-bold text-xs">Cancel</button>
                                    </div>
                                </form>
                            </div>

                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        let holdTimer;
        let holdInterval;
        let holdPercent = 0;
        let currentTarget = null;
        let isEditing = false;

        // Prevent flipping if we are in edit mode
        function handleCardClick(el, e) {
            if (isEditing || el.classList.contains('cursor-not-allowed')) return;
            el.classList.toggle('flipped');
        }

        // --- LONG PRESS LOGIC ---
        function startHold(el) {
            const inner = el.closest('.card-inner');
            if (!inner.classList.contains('flipped') || isEditing) return;

            currentTarget = el;
            holdPercent = 0;
            const bar = el.querySelector('.hold-indicator');

            holdInterval = setInterval(() => {
                holdPercent += 2; 
                bar.style.width = holdPercent + '%';
                
                if (holdPercent >= 100) {
                    clearHold();
                    enterEditMode(el);
                }
            }, 60); // 3000ms total
        }

        function clearHold() {
            clearInterval(holdInterval);
            if (currentTarget) {
                currentTarget.querySelector('.hold-indicator').style.width = '0%';
            }
            holdPercent = 0;
        }

        function enterEditMode(el) {
            isEditing = true;
            el.classList.add('editing-mode');
            el.querySelector('.content-display').classList.add('hidden');
            el.querySelector('.edit-form').classList.remove('hidden');
            
            // Focus the textarea automatically
            const txt = el.querySelector('textarea');
            txt.focus();
            txt.setSelectionRange(txt.value.length, txt.value.length);
        }

        function cancelEdit(btn) {
            event.stopPropagation();
            const el = btn.closest('.card-back');
            isEditing = false;
            el.classList.remove('editing-mode');
            el.querySelector('.content-display').classList.remove('hidden');
            el.querySelector('.edit-form').classList.add('hidden');
        }

        // Rest of your functions (randomSelect, etc)
        function randomSelect() {
            const available = document.querySelectorAll('.card-container[data-taken="false"]');
            if (available.length === 0) return alert("No available cards!");
            const randomIndex = Math.floor(Math.random() * available.length);
            const card = available[randomIndex].querySelector('.card-inner');
            available[randomIndex].scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(() => card.classList.add('flipped'), 500);
        }
    </script>
</body>
</html>
