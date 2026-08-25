import subprocess
import os

authors = [
    "anillovescoding <anillovescoding@users.noreply.github.com>",
    "Aanshhuu <aanshhuu@users.noreply.github.com>"
]

# Get the last 5 commit hashes in reverse chronological order
result = subprocess.run(["git", "log", "--format=%H", "-n", "5"], capture_output=True, text=True)
commits = result.stdout.strip().split('\n')
commits.reverse() # Oldest to newest

# Create a temporary branch at HEAD~5
subprocess.run(["git", "checkout", "-b", "rewrite", "main~5"])

for i, commit in enumerate(commits):
    author = authors[i % 2]
    # Cherry pick the commit
    subprocess.run(["git", "cherry-pick", commit])
    # Amend the author
    subprocess.run(["git", "commit", "--amend", f"--author={author}", "--no-edit"])

# Replace main with rewrite
subprocess.run(["git", "checkout", "main"])
subprocess.run(["git", "reset", "--hard", "rewrite"])
subprocess.run(["git", "branch", "-D", "rewrite"])
subprocess.run(["git", "push", "-f", "origin", "main"])
print("History rewritten and force pushed successfully!")
