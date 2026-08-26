# PPタスク管理 v2

今回の既存ファイルを前提に、ログイン系と旧タスク管理を統合した版です。

## 重要な変更
- 既存の `user_tbl` / `remember_token_tbl` をそのまま利用
- `$_SESSION["user_account"]` のメールアドレスをタスク作成者として自動記録
- CSVをタスク本体の保存先にしない
- プロジェクトごとにタスクをDB管理
- コメントと詳細メモを分離
- プロジェクトごとにメンバーを管理
- 管理者はメンバー追加・強制脱退・管理者変更・プロジェクト削除が可能
- 管理者の脱退には新管理者の指定が必須
- 全操作を活動ログへ記録
- 活動ログをTXT出力
- 既存のサムネイル・memo.txt・attachmentsのUIを維持
- プロジェクトごとに保存先設定の土台を用意
- Windows/Raspberry Pi用PP Task Storage Serverを同梱

## 最初にやること
1. `config.php` のDB情報を学校環境に合わせる
2. `database/schema.sql` を実行
3. `login.php` を開く
4. 新規プロジェクトを作る
5. メンバー管理から別ユーザーのメールアドレスを許可
6. タスクを作成して、編集・コメント・添付・削除を確認

## 既存CSVの移行
`database/migrate_csv.php` は既存 `Data/task_list.csv` を新規プロジェクトへ移行する補助ツールです。
移行前に必ず `Data` をバックアップしてください。
移行後は `migrate_csv.php` を削除してください。

## 保存サーバーについて
今のv2では「保存先設定」と「Storage Server本体」を用意しています。
ただし、タスクの画像/添付をHTTP Storage Serverへ実際に送る処理はまだ接続していません。
まず学校サーバー上でDB方式を安定させ、その後Storage Providerを接続するのが安全です。

GitHub / Google Driveも同じ保存先プロバイダー方式で後から追加できます。
