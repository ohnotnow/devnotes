The idea I want to capture is a bit like a wiki but much easier to use and much more scrappy.  So maybe like a wiki meets GitHub gists.  The main idea is for on the ground developers to be able to capture little gotchas, ideas, things that worked, things that didn't.  This is Laravel application, as our development team are Laravel developers.  But we would want to offer an API and a nice easy CLI tool at some point.  something that would let them very quickly capture little snippets of information or bits of conversation with Claude.

We also want a pleasant web UI for people to be able to manage notes, edit, create, all the crud stuff.  We have Livewire and flux UI available. 

At the moment I'm not sure about authentication, authorisation, who should be able to delete a note or edit a note?  Authentication will be done through our own SSO provider though.  We just need a bit of UI to create an allow-list.  We also need a UI for API keys to allow developers to create sanctum tokens.

Thoughts, questions?
