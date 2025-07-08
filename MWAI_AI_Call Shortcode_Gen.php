function mwai_question_button_shortcode($atts) {
    $atts = shortcode_atts([
        'question_id' => '',
    ], $atts);

    if (empty($atts['question_id'])) {
        return '<div style="color:red;">Error: question_id attribute is required.</div>';
    }

    $question_id = esc_attr($atts['question_id']);
    $nonce = wp_create_nonce('wp_rest');
    static $counter = 0;
    $counter++;

    ob_start();
    ?>
    <div class="mwai-question-wrapper" id="mwai-wrapper-<?php echo $counter; ?>">
        <label for="mwai-council-<?php echo $counter; ?>"><strong>Select a Council:</strong></label>
        <select id="mwai-council-<?php echo $counter; ?>" style="width:100%; margin-bottom:10px; padding:6px;">
            <option value="">Loading councils...</option>
        </select>

        <button id="mwai-generate-btn-<?php echo $counter; ?>" style="margin-top:5px;">Generate Response</button>
        <div id="mwai-response-<?php echo $counter; ?>" style="margin-top:15px; padding:10px; border:1px solid #ccc; border-radius:5px;"></div>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", () => {
        const dropdown = document.getElementById("mwai-council-<?php echo $counter; ?>");
        const btn = document.getElementById("mwai-generate-btn-<?php echo $counter; ?>");
        const responseBox = document.getElementById("mwai-response-<?php echo $counter; ?>");
        const questionEl = document.getElementById("<?php echo $question_id; ?>");

        fetch("https://yourbard.com/wp-content/uploads/2025/07/uk-district-councils.json")
            .then(res => res.json())
            .then(councils => {
                dropdown.innerHTML = '<option value="">-- Select Council --</option>';
                councils.forEach(c => {
                    const opt = document.createElement("option");
                    opt.value = c;
                    opt.textContent = c;
                    dropdown.appendChild(opt);
                });
            })
            .catch(err => {
                console.error(err);
                dropdown.innerHTML = '<option value="">Failed to load councils</option>';
            });

        btn.addEventListener("click", async () => {
            if (!questionEl) {
                responseBox.innerHTML = "<em style='color:red;'>Error: Question element not found.</em>";
                return;
            }

            const council = dropdown.value;
            if (!council) {
                responseBox.innerHTML = "<em>Please select a council.</em>";
                return;
            }

            let userQuestion = questionEl.innerText || questionEl.value || "";
            if (!userQuestion.includes("[INSERT COUNCIL NAME]")) {
                responseBox.innerHTML = "<em>Please include [INSERT COUNCIL NAME] in your question.</em>";
                return;
            }

            const finalPrompt = `
System: You are an assistant helping with UK council policy. Provide clear, factual answers. Do not ask follow-up or leading questions. End conclusively.
User: ${userQuestion.replace(/\[INSERT COUNCIL NAME\]/g, council)}
`;

            responseBox.innerHTML = "<em>Generating response...</em>";

            try {
                const res = await fetch('<?php echo esc_url(rest_url("mwai/v1/simpleChatbotQuery")); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': '<?php echo $nonce; ?>'
                    },
                    body: JSON.stringify({
                        prompt: finalPrompt.trim(),
                        botId: 'default'
                    })
                });

                if (!res.ok) {
                    const errorText = await res.text();
                    throw new Error(`HTTP ${res.status}: ${errorText}`);
                }

                const data = await res.json();
                let reply = data?.data || data?.response || data;

                if (typeof reply !== "string") {
                    reply = JSON.stringify(reply, null, 2);
                }

                // Markdown & formatting
                reply = reply
                    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                    .replace(/\[(.*?)\]\((.*?)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>')
                    .replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>')
                    .replace(/^### (.*$)/gim, '<h3>$1</h3>')
                    .replace(/^## (.*$)/gim, '<h2>$1</h2>')
                    .replace(/^# (.*$)/gim, '<h1>$1</h1>')
                    .replace(/^\-\s(.*$)/gim, '<li>$1</li>')
                    .replace(/<\/li><br>/g, '</li>')
                    .replace(/<br><li>/g, '<ul><li>')
                    .replace(/<\/li><br>/g, '</li></ul><br>')
                    .replace(/\n{1,2}/g, '<br>');

                responseBox.innerHTML = reply;
            } catch (e) {
                responseBox.innerHTML = "<em style='color:red;'>Request failed: " + e.message + "</em>";
            }
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('mwai_question_button', 'mwai_question_button_shortcode');
