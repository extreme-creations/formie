# Form Templates
Formie comes with all the required Twig templates to make your forms look great. Additionally, we provide CSS and JS outputted alongside to form to ensure you can use forms out-of-the-box with no configuration. Read more about [Form Templates](docs:feature-tour/form-templates).

## Custom Templates
While Formie's default templates suit most needs, you can of course roll your own templates, so you have total control over the form, field, layout and more.

:::warning
By overriding template files, you will no longer receive bug fixes and improvements. If breaking changes are introduced, you will need to update your own templates. For more information on how to customize templates without overriding template files, please refer to the [hooks documentation](docs:developers/hooks).
:::

The great thing about Formie's custom templates is that it doesn't have to be all-or-nothing. You can choose to override a single template, or all. For instance, you might have very specific markup needs to a Select field. You can override just the template for the select field, and nothing else.

To get started, navigate to **Formie** → **Settings** → **Form Templates** and create a new template. If you're going to use **Use Custom Template**, we recommend you select **Copy Templates** when creating, so you can remove any HTML template you're not overriding, which will resolve back to Formie's defaults. That way, you're starting off with the templates Formie already uses as a basis for your custom templates.

:::tip
You can't modify Formie's default Form Templates. Instead, you'll want to create a new Form Template, and ensure your forms use that. This gives you the benefit of being able to easily manage _multiple_ custom templates across your forms.
:::

Before we dive in, it's worth taking the time to understand the structure of how templates go together.

:::tip
We're using the `.html` extension here for clarity. You can use `.twig` or whatever you have set in your [defaultTemplateExtensions](https://docs.craftcms.com/v3/config/config-settings.html#defaulttemplateextensions) for the actual files.
:::

- `form.html`
- `field.html`
- `page.html`
- `_includes/`
    - `label.html`
    - `submit.html`
    - `...`
- `fields/`
    - `address/`
        - `country.html`
        - `...`
    - `agree.html`
    - `categories.html`
    - `...`

Let's start with the top-level templates.

:::tip
Check out the raw templates on [Formie's GitHub](https://github.com/verbb/formie/tree/craft-3/src/templates/_special) - they'll be the most up to date.
:::

## Overriding Form Templates
To override the form template, provide a file named `form.html`.

### Available Template Variables
Field templates have access to the following variables:

Variable | Description
--- | ---
`form` | A [Form](docs:developers/form) object, for the form instance this template is for.
`options` | A collection of additional options.
`submission` | The current [Submission](docs:developers/submission) object this this form may or may not have.

## Overriding Page Templates
To override the page template, provide a file named `page.html`.

### Available Template Variables
Field templates have access to the following variables:

Variable | Description
--- | ---
`form` | A [Form](docs:developers/form) object that this field belongs to.
`page` | A [Page](docs:developers/page) object, for the page instance this template is for.
`options` | A collection of additional options.

## Overriding Field Wrapper Templates
To override the field template, provide a file named `field.html`. This is the wrapper template around all fields. You can also override individual field types' templates, rather than changing the template for every field, regardless of type.

### Available Template Variables
Field templates have access to the following variables:

Variable | Description
--- | ---
`form` | A [Form](docs:developers/form) object that this field belongs to.
`field` | A [Field](docs:developers/field) object, for the field instance this template is for.
`handle` | The handle of the field.
`options` | A collection of additional options, available for some fields.
`element` | The current [Submission](docs:developers/submission) object this this form may or may not have.

## Overriding Field Templates
You'll notice the above structure includes the `fields/` directory. Inside this directory are a mixture of folders and individual files, each representing a template that you're able to override.

First, you'll need to identify the template's name. It's derived from the PHP class name for the field, converted to a "kebab" string. For easy reference, you can use the below table.

Class Name | Template
--- | ---
`Address` | `address.html`
`Agree` | `agree.html`
`Calculations` | `calculations.html`
`Categories` | `categories.html`
`Checkboxes` | `checkboxes.html`
`Date` | `date.html`
`Dropdown` | `dropdown.html`
`Email` | `email.html`
`Entries` | `entries.html`
`FileUpload` | `file-upload.html`
`Group` | `group.html`
`Heading` | `heading.html`
`Hidden` | `hidden.html`
`Html` | `html.html`
`MultiLineText` | `multi-line-text.html`
`Name` | `name.html`
`Number` | `number.html`
`Password` | `password.html`
`Phone` | `phone.html`
`Products` | `products.html`
`Radio` | `radio.html`
`Recipients` | `recipients.html`
`Repeater` | `repeater.html`
`Section` | `section.html`
`Signature` | `signature.html`
`SingleLineText` | `single-line-text.html`
`Summary` | `summary.html`
`Table` | `table.html`
`Tags` | `tags.html`
`Users` | `users.html`
`Variants` | `variants.html`

Adding a template file in your specified template directory will use that template file over the ones Formie provide.

You might also have noticed we've shown `address` in a folder. Due to how Twig resolves templates, the below are equivalent:

```
fields/address.html - Is the same as - fields/address/index.html
```

For complex fields that have multiple templates, we've used folders to organise multiple templates in a single folder. You're welcome to follow this same pattern, but you're not forced to.

For example, the Address field, has the following templates in a folder:

- `fields/address/_country.html`
- `fields/address/_field.html`
- `fields/address/_input.html`
- `fields/address/index.html`

This is because the address field has many parts, and is complex. If you want to override the templates for this field, you just need to alter the `index.html` file. You can use the includes (denoted by `_`), or you don't have to.

## Overriding Partials
You'll have noticed in our preview of the templates' directory, the inclusion of an `_includes` directory. This houses partial templates that are used throughout the templates. This helps not only with re-use, but keeps things modular, which has a flow-on effect when you want to override _just_ a partial.

The `form.html` file sets up your form, but also includes other partials like `_includes/page-tabs.html`, `_includes/progress.html` and  `_includes/submit.html`. Rather than overriding the `form.html` file just to alter any one of these partials, you can override just the partial.

For example, let's say we want to override the page tabs of a multi-step form. We could create a file `_includes/page-tabs.html` and add our content to this template. There's no need to override `form.html` now!

### How it Works
Formie's templates use a custom Twig function like `{{ formieInclude('_includes/page-tabs') }}`. This is in contrast to what you might be used to in your own templates, something like `{% include '_includes/page-tabs' %}`. The drawback with this latter approach is how Formie resolves the template partial. Using `{% include %}` it will expect to find the template partial relative to the template file you're including it from. Instead, `formieInclude()` will resolve the template partial to either your overrides' folder, or Formie's default templates.
