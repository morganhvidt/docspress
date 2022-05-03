const { registerBlockType } = wp.blocks;

const { Placeholder } = wp.components;

registerBlockType('docspress/archive', {
  title: 'DocsPress Archive',
  description: 'FullSite Editing template for DocsPress Archive',
  supports: {
    align: true,
    className: true,
  },
  icon: 'media-document',
  edit: () => (
    <Placeholder
      icon="media-document"
      label="DocsPress Archive Block"
      instructions="This is an editor placeholder for the DocsPress Archive Block. In your documentation this will be replaced by the template and display with your article image(s), title, etc. You can move this placeholder around and add further blocks around it to extend the template."
    />
  ),
  save() {
    return null; // Nothing to save here..
  },
});
