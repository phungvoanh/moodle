@editor @editor_tiny @javascript
Feature: Use MathML elements in TinyMCE
  In order to use MathML elements in TinyMCE
  As a user
  I need to be able to save MathML without unwanted inline display styles

  Background:
    Given I am on the "Profile advanced editing" page logged in as "admin"

  Scenario: MathML elements do not get inline display style added on save
    When I set the field "Description" to multiline:
      """
      <math>
        <msub>
          <mi>P</mi>
          <mi>z</mi>
        </msub>
        <mo>=</mo>
        <msub>
          <mi>P</mi>
          <mi>i</mi>
        </msub>
        <mo>·</mo>
        <msub>
          <mi>k</mi>
          <mi>j</mi>
        </msub>
      </math>
      """
    And I click on the "Tools > Source code" menu item for the "Description" TinyMCE editor
    And I click on "Save" "button"
    # Save a second time to verify idempotency.
    And I click on the "Tools > Source code" menu item for the "Description" TinyMCE editor
    And I click on "Save" "button"
    Then the field "Description" matches expression "#^\s*<math>\s*<msub>.*</math>\s*$#s"
    And the field "Description" does not match expression "#<math[^>]*style=#s"
    And the field "Description" does not match expression "#<br#i"

  Scenario: MathML display attribute and complex elements are preserved without inline style
    When I set the field "Description" to multiline:
      """
      <math display="block">
        <mfrac>
          <msqrt>
            <mi>x</mi>
          </msqrt>
          <mn>2</mn>
        </mfrac>
        <mo>=</mo>
        <mtable>
          <mtr>
            <mtd><mn>1</mn></mtd>
            <mtd><mn>0</mn></mtd>
          </mtr>
        </mtable>
      </math>
      <p>Inline: <math display="inline"><mi>y</mi></math></p>
      """
    And I click on the "Tools > Source code" menu item for the "Description" TinyMCE editor
    And I click on "Save" "button"
    Then the field "Description" matches expression "#<math display=\"block\">\s*<mfrac>\s*<msqrt>.*<mtable>.*<mtr>.*<mtd>.*</math>#s"
    And the field "Description" matches expression "#<p>Inline:\s*<math display=\"inline\">\s*<mi>y</mi>\s*</math>\s*</p>#s"
    And the field "Description" does not match expression "#<math[^>]*style=#s"
    And the field "Description" does not match expression "#<br#i"

  Scenario: Inline MathML inside paragraph and custom MathML styles are preserved
    When I set the field "Description" to multiline:
      """
      <p>Equation <math><mi>a</mi><mo>+</mo><mi>b</mi></math> end</p>
      <p><math style="color: red;"><mi>c</mi></math></p>
      """
    And I click on the "Tools > Source code" menu item for the "Description" TinyMCE editor
    And I click on "Save" "button"
    Then the field "Description" matches expression "#<p>Equation\s*<math>\s*<mi>a</mi>\s*<mo>\+</mo>\s*<mi>b</mi>\s*</math>\s*end</p>#s"
    And the field "Description" matches expression "#<math style=\"color:\s*red;?\">\s*<mi>c</mi>\s*</math>#s"
    And the field "Description" does not match expression "#<math[^>]*display:#s"
    And the field "Description" does not match expression "#<br#i"
