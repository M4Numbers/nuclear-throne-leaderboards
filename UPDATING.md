# What's Happening Right Now?

Currently, the repository is going through a multi-stage improvement and tidy-up process, carried
out by @M4Numbers, who is going through and enforcing some general standards that will make the
code base nicer to work with in the future.

These improvements can be categorised into four categories:

## Reformatting

Previously, the repository was kind of a mess. The aim of the first stage is to go through and make
sure that all files follow the same formatting guidelines, using tabs **or** spaces throughout the
whole codebase is an example of this.

**This stage is currently: Completed**

## Commenting

In order to make this repository easier for anyone else to pick up and look through, the second part
of the improvements will be a mass commenting of the codebase (sarcasm and cutting jibes are completely
optional and also expected), which will mean that the majority of the code is legible and any programmer
will be able to look at an area of the code and know exactly what it does.

**This stage is currently: In Progress**

## Improving

The code works at the moment, this is certainly true, but the third stage of this process will be
focusing on the idea that it could always work better. A lot of code in the codebase is duplicated
throughout, the most prominent of which seems to be database instantiation, which seems to make an
appearance in nearly every file of the codebase.

The improvements done to the codebase should hopefully enable for a cleaner codebase that has a minimal
amount of duplication and has been upgraded to shed any weight that doesn't need to be there. It should
also be noted that after (or during) this stage, another round of commenting will take place with the
new and improved code. In addition, further stylistic changes will take place during this stage as,
during the previous two stages, very little of the code should have been worked on.

**This stage is currently: To Be Completed**

## Testing

The final step in this process will be the culmination of the previous stage (and, to an extent, the
commenting stage too), where all functions will be wrung through a testing framework to make sure that
they perform to a suitable standard. The standard in this case will probably a PHPUnit testing framework
which will provide one successful test for each function and **at least one** failing test for each
function.*

\* Excluding those functions which can take no erroneous parameters.

**This stage is currently: To Be Completed**