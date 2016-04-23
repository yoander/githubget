##  GitHub Get: a plugin to fetch content from GitHub.

GitHub Get is a WordPress plugin for fetch content from GitHub. GitHub Get use GitHub personal token and basic authentication to access the GitHub API 

## How to use it

In order to use GitHub Get you mus insert a shortcode in your Post or Page.

### Gists

One file per Gists

```
[githubget]GistsID[/githubget]
```

Multiple file per Gists

```
[githubget filename="Name of the file"]GistsID[/githubget]
```

If you not specify a filename then the content of the first one will be get it.

### Content from a repo

```
[githubget repo="1"]FilePath[/githubget]
```

Note: repo attribute is mandatory

To find the FilePath browse your repo from GitHub find the file and use Copy path GitHub function, you
shouldi not paste the Path in Wordpress Visual Editor due will copy the full path including GitHub Url I
recommend you to paste it in Text Editor.
