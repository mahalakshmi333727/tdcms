-- Test the actual PHP completion logic
SELECT 'Step 1: Create test task' as step;
INSERT INTO assignments (user_id, title, category, text_content, reference_link, tags, assigned_by, status, text_copied, link_clicked) 
VALUES (3, 'PHP Test Task', 'Testing', 'Test content here', 'https://example.com', 'test', 1, 'pending', 0, 0);

SELECT 'Step 2: Initial state' as step;
SELECT id, text_copied, link_clicked, status FROM assignments WHERE title = 'PHP Test Task';

-- Simulate the recordTaskProgress logic for copy action
SET @assignId = (SELECT id FROM assignments WHERE title = 'PHP Test Task');
UPDATE assignments 
SET text_copied = 1
WHERE id = @assignId;

SELECT 'Step 3: After text copy (should still be pending)' as step;
SELECT id, text_copied, link_clicked, status FROM assignments WHERE id = @assignId;

-- Simulate the recordTaskProgress logic for link click
UPDATE assignments 
SET link_clicked = 1,
    status = CASE WHEN (text_copied = 1 AND 1 = 1) THEN 'completed' ELSE 'pending' END,
    completed_at = CASE WHEN (text_copied = 1 AND 1 = 1) THEN NOW() ELSE NULL END
WHERE id = @assignId;

SELECT 'Step 4: After link click (should be completed)' as step;
SELECT id, text_copied, link_clicked, status FROM assignments WHERE id = @assignId;
