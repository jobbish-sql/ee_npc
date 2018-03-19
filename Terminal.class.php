<?php
/**
 * This class is for terminal output for the EE NPC's
 *
 * PHP Version 7
 *
 * @category Classes
 * @package  EENPC
 * @author   Julian Haagsma aka qzjul <jhaagsma@gmail.com>
 * @license  All EENPC files are under the MIT License
 * @link     https://github.com/jhaagsma/ee_npc
 */

namespace EENPC;

/*
IDEA FOR USEING out() BUT USING TERMINAL!

https://stackoverflow.com/questions/7141745/alias-class-method-as-global-function
You can either create a wrapper function or use create_function().

function _t() {
    call_user_func_array(array('TranslationClass', '_t'), func_get_args());
}

Or you can create a function on the fly:

$t = create_function('$string', 'return TranslationClass::_t($string);');

// Which would be used as:
print $t('Hello, World');
*/

function out()
{
    call_user_func_array(['Terminal', 'out'], func_get_args());
}//end out()


function out_data()
{
    call_user_func_array(['Terminal', 'out_data'], func_get_args());
}//end out_data()


class Terminal
{
    /**
     * Ouput strings nicely
     * @param  string  $str              The string to format
     * @param  boolean $newline          If we shoudl make a new line
     * @param  string  $foreground_color Foreground color
     * @param  string  $background_color Background color
     *
     * @return void             echoes, not returns
     */
    public static function out($str, $newline = true, $foreground_color = null, $background_color = null)
    {
        //This just formats output strings nicely
        if (is_object($str)) {
            return out_data($str);
        }

        if ($foreground_color || $background_color) {
            $str = Colors::getColoredString($str, $foreground_color, $background_color);
        }

        echo ($newline ? "\n" : null)."[".date("H:i:s")."] $str";
    }//end out()




    /**
     * Output and format data
     * @param  array,object $data Data to ouput
     * @return void
     */
    public static function out_data($data)
    {
        $backtrace = debug_backtrace();
        //out(var_export($backtrace, true));
        //This function is to output and format some data nicely
        out("DATA: ({$backtrace[0]['file']}:{$backtrace[0]['line']})\n".json_encode($data));
        //str_replace(",\n", "\n", var_export($data, true)));
    }//end out_data()
}//end class


    //add this and the other ones later...
    //<rule ref="Squiz.WhiteSpace.ControlStructureSpacing"/>

/*
<?xml version="1.0" encoding="UTF-8"?>
<ruleset name="emphyre">
<description>The PHPCS standard, minus some breaking things.
</description>
    <!-- Include the whole PSR-1 standard -->
    <rule ref="PSR1"/>
    <!-- Include the whole PSR-2 standard -->
    <rule ref="PSR2"/>

<rule ref="Generic.ControlStructures.InlineControlStructure"/>
<rule ref="Generic.Files.LineEndings"/>
<rule ref="Generic.Formatting.DisallowMultipleStatements"/>
<rule ref="Generic.Formatting.MultipleStatementAlignment">
    <properties>
        <property name="error" value="false"/>
        <property name="maxPadding" value="40"/>
    </properties>
</rule>
<rule ref="Generic.Formatting.NoSpaceAfterCast"/>
<rule ref="Generic.Functions.CallTimePassByReference"/>
<rule ref="Generic.Functions.FunctionCallArgumentSpacing"/>
<rule ref="Generic.Metrics.CyclomaticComplexity"/>
<rule ref="Generic.Metrics.NestingLevel"/>
<rule ref="Generic.NamingConventions.ConstructorName"/>
<rule ref="Generic.NamingConventions.UpperCaseConstantName"/>
<rule ref="Generic.PHP.DeprecatedFunctions"/>
<rule ref="Generic.PHP.DisallowShortOpenTag"/>
<rule ref="Generic.PHP.ForbiddenFunctions">
    <properties>
        <property name="error" value="false"/>
    </properties>
</rule>
<rule ref="Generic.PHP.LowerCaseConstant"/>
<rule ref="Generic.PHP.NoSilencedErrors">
    <properties>
        <property name="error" value="false"/>
    </properties>
</rule>
<rule ref="Generic.WhiteSpace.DisallowTabIndent"/>
<rule ref="PEAR.Classes.ClassDeclaration"/>
<rule ref="PEAR.Commenting.FileComment"/>
<rule ref="PEAR.Commenting.FunctionComment"/>
<rule ref="PEAR.Commenting.InlineComment"/>
<rule ref="PEAR.Files.IncludingFile"/>
<rule ref="PEAR.Formatting.MultiLineAssignment"/>
<rule ref="PEAR.Functions.FunctionCallSignature"/>
<rule ref="PEAR.Functions.ValidDefaultValue"/>
<rule ref="PEAR.WhiteSpace.ScopeClosingBrace"/>
<rule ref="Squiz.PHP.DisallowObEndFlush"/>
<rule ref="Squiz.PHP.DisallowSizeFunctionsInLoops"/>
<rule ref="Squiz.PHP.DiscouragedFunctions"/>
<rule ref="Squiz.PHP.Eval"/>
<rule ref="Squiz.PHP.ForbiddenFunctions"/>
<rule ref="Squiz.PHP.GlobalKeyword"/>
<rule ref="Squiz.PHP.InnerFunctions"/>
<rule ref="Squiz.PHP.LowercasePHPFunctions"/>
<rule ref="Squiz.Scope.StaticThisUsage"/>
<rule ref="Squiz.WhiteSpace.CastSpacing"/>
<rule ref="Squiz.WhiteSpace.ControlStructureSpacing"/>
<rule ref="Squiz.WhiteSpace.LanguageConstructSpacing"/>
<rule ref="Squiz.WhiteSpace.LogicalOperatorSpacing"/>
<rule ref="Squiz.WhiteSpace.ObjectOperatorSpacing"/>
<rule ref="Squiz.WhiteSpace.OperatorSpacing"/>
<rule ref="Squiz.WhiteSpace.PropertyLabelSpacing"/>
<rule ref="Zend.Debug.CodeAnalyzer"/>
<rule ref="Squiz.Commenting.ClosingDeclarationComment" />
</ruleset>
 */